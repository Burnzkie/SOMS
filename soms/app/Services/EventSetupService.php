<?php
// app/Services/EventSetupService.php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared event-creation logic — used by both Officer\EventController
 * (web quick-create modal + full create form) and Api\Officer\EventController
 * (Flutter quick-create sheet), so the day/session/fine-rule generation
 * rules can't drift between the two entry points.
 *
 * Attendance setup: full_day (Morning + Afternoon), short (one session) or custom
 * (several named sessions) — short/custom sessions are stored with session_type 'custom'.
 *
 * Audience: events.target_programs limits which programs must attend (null = all).
 *
 * Schedule model: every day gets a Morning and an Afternoon session, each
 * with its own time-in window and an optional time-out window. A "parade
 * date" is simply a day whose Morning session is flagged is_parade — its
 * time-in is the parade check-in and uses the 'missed_parade' fine rule.
 */
class EventSetupService
{
    public const PERIODS = ['morning', 'afternoon'];
    public const WINDOW_FIELDS = ['timein_start', 'timein_end', 'timeout_start', 'timeout_end'];

    /** Used when a form/API client sends no times for a period. */
    public const DEFAULT_WINDOWS = [
        'morning'   => ['timein_start' => '07:00', 'timein_end' => '08:00', 'timeout_start' => '11:30', 'timeout_end' => '12:30'],
        'afternoon' => ['timein_start' => '13:00', 'timein_end' => '14:00', 'timeout_start' => '17:00', 'timeout_end' => '18:00'],
    ];

    /** Request keys that describe the schedule (split off before saving the event row). */
    public const SCHEDULE_KEYS = [
        'morning_timein_start', 'morning_timein_end', 'morning_timeout_start', 'morning_timeout_end',
        'afternoon_timein_start', 'afternoon_timein_end', 'afternoon_timeout_start', 'afternoon_timeout_end',
        'parade_dates', 'parade_dates_submitted', 'parade_mode',
        'target_programs', 'target_programs_submitted',
        'attendance_setup', 'custom_sessions',
    ];

    /**
     * Validation rules for the schedule fields, shared by web + API.
     * Per period: time-in pair is required together; time-out pair is
     * optional but must be a complete pair that comes after time-in.
     */
    public static function scheduleRules(): array
    {
        $rules = [
            'parade_dates'           => 'nullable|array|max:60',
            'parade_dates.*'         => 'date_format:Y-m-d|distinct',
            'parade_dates_submitted' => 'nullable|boolean',
            'parade_mode'            => 'nullable|in:none,first,last,all',
            'target_programs'           => 'nullable|array|max:100',
            'target_programs.*'         => 'string|max:100',
            'target_programs_submitted' => 'nullable|boolean',

            // full_day = Morning + Afternoon, short = one session, custom = any number of named sessions
            'attendance_setup' => 'nullable|in:full_day,short,custom',
            'custom_sessions'  => 'required_if:attendance_setup,short,custom|array|min:1|max:10',
            'custom_sessions.*.name'          => 'nullable|string|max:60',
            'custom_sessions.*.timein_start'  => 'required|date_format:H:i',
            'custom_sessions.*.timein_end'    => 'required|date_format:H:i|after:custom_sessions.*.timein_start',
            'custom_sessions.*.timeout_start' => 'nullable|date_format:H:i|required_with:custom_sessions.*.timeout_end|after:custom_sessions.*.timein_end',
            'custom_sessions.*.timeout_end'   => 'nullable|date_format:H:i|required_with:custom_sessions.*.timeout_start|after:custom_sessions.*.timeout_start',
        ];

        foreach (self::PERIODS as $p) {
            $rules["{$p}_timein_start"]  = "nullable|date_format:H:i|required_with:{$p}_timein_end,{$p}_timeout_start,{$p}_timeout_end";
            $rules["{$p}_timein_end"]    = "nullable|date_format:H:i|required_with:{$p}_timein_start|after:{$p}_timein_start";
            $rules["{$p}_timeout_start"] = "nullable|date_format:H:i|required_with:{$p}_timeout_end|after:{$p}_timein_end";
            $rules["{$p}_timeout_end"]   = "nullable|date_format:H:i|required_with:{$p}_timeout_start|after:{$p}_timeout_start";
        }

        return $rules;
    }

    /** Flat 8-key default window (morning_timein_start => '07:00', ...) for prefilling forms. */
    public static function defaultTimeWindow(): array
    {
        $flat = [];
        foreach (self::DEFAULT_WINDOWS as $period => $window) {
            foreach ($window as $field => $time) {
                $flat["{$period}_{$field}"] = $time;
            }
        }

        return $flat;
    }

    /**
     * @param  array  $data  title, description, venue, type, image_path, color, date_start, date_end,
     *                       plus optional schedule keys (see SCHEDULE_KEYS) and legacy has_parade
     */
    public static function create(array $data, int $organizationId, int $createdById): Event
    {
        return DB::transaction(function () use ($data, $organizationId, $createdById) {
            $setup = self::resolveSetup($data);
            $isFullDay = $setup === 'full_day';

            // Parade dates only apply to the Morning session of a full-day event.
            $paradeDates = $isFullDay ? self::resolveParadeDates($data, $data['date_start'], $data['date_end']) : [];
            $plan = $isFullDay ? self::fullDayPlan($data) : self::customPlan($data, $setup);

            $event = Event::create([
                'organization_id' => $organizationId,
                'created_by'      => $createdById,
                'title'           => $data['title'],
                'description'     => $data['description'] ?? null,
                'venue'           => $data['venue'] ?? null,
                'type'            => $data['type'],
                'image_path'      => $data['image_path'] ?? null,
                'color'           => $data['color'] ?? '#FF7A29',
                'date_start'      => $data['date_start'],
                'date_end'        => $data['date_end'],
                'has_parade'      => !empty($paradeDates),
                'target_programs' => self::resolveTargetPrograms($data),
            ]);

            $dateRange = Carbon::parse($data['date_start'])->toPeriod(Carbon::parse($data['date_end']));

            foreach ($dateRange as $date) {
                $day = EventDay::create([
                    'event_id' => $event->id,
                    'date'     => $date->toDateString(),
                ]);

                self::createSessions($day, $date, in_array($date->toDateString(), $paradeDates, true), $plan);
            }

            // Default fine rule amounts (₱0) so the table exists — officer edits amounts next.
            foreach (self::fineViolationsFor($plan, !empty($paradeDates)) as $violation) {
                EventFineRule::firstOrCreate(
                    ['event_id' => $event->id, 'violation_type' => $violation],
                    ['amount' => 0]
                );
            }

            return $event;
        });
    }

    /** 'full_day' (default), 'short' (one session) or 'custom' (several named sessions). */
    protected static function resolveSetup(array $data): string
    {
        $setup = $data['attendance_setup'] ?? 'full_day';

        return in_array($setup, ['short', 'custom'], true) ? $setup : 'full_day';
    }

    /** The Morning + Afternoon sessions of a classic full-day event. */
    protected static function fullDayPlan(array $data): array
    {
        $plan = [];
        foreach (self::PERIODS as $index => $period) {
            $plan[] = [
                'session_type' => $period,
                'name'         => null,
                'sort_order'   => $index + 1,
                'window'       => self::resolvePeriodWindow($data, $period),
            ];
        }

        return $plan;
    }

    /**
     * Sessions of a short event (exactly one) or a multiple-activities event
     * (up to 10, in the order submitted). The same sessions repeat on every day.
     */
    protected static function customPlan(array $data, string $setup): array
    {
        $rows = array_values($data['custom_sessions'] ?? []);
        if ($setup === 'short') {
            $rows = array_slice($rows, 0, 1);
        }

        if (empty($rows)) {
            throw new \InvalidArgumentException('A short or custom event needs at least one session.');
        }

        $plan = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            $plan[] = [
                'session_type' => 'custom',
                'name'         => $setup === 'short' ? 'Attendance' : ($name !== '' ? $name : 'Session ' . ($index + 1)),
                'sort_order'   => $index + 1,
                'window'       => [
                    'timein_start'  => $row['timein_start'] ?? null,
                    'timein_end'    => $row['timein_end'] ?? null,
                    'timeout_start' => ($row['timeout_start'] ?? null) ?: null,
                    'timeout_end'   => ($row['timeout_end'] ?? null) ?: null,
                ],
            ];
        }

        return $plan;
    }

    /** Fine rules an event needs: the four Morning/Afternoon ones (+ parade), or the shared custom ones. */
    protected static function fineViolationsFor(array $plan, bool $hasParade): array
    {
        if ($plan[0]['session_type'] !== 'custom') {
            $violations = ['missed_morning_timein', 'missed_morning_timeout', 'missed_afternoon_timein', 'missed_afternoon_timeout'];

            return $hasParade ? array_merge($violations, ['missed_parade']) : $violations;
        }

        $violations = ['missed_custom_timein'];
        if (collect($plan)->contains(fn (array $spec) => !empty($spec['window']['timeout_start']))) {
            $violations[] = 'missed_custom_timeout';
        }

        return $violations;
    }

    /**
     * Creates one day's sessions from the plan. On a parade date the Morning
     * session carries the parade flag.
     */
    protected static function createSessions(EventDay $day, Carbon $date, bool $isParadeDay, array $plan): void
    {
        foreach ($plan as $spec) {
            $window = $spec['window'];

            EventSession::create([
                'event_day_id'  => $day->id,
                'session_type'  => $spec['session_type'],
                'name'          => $spec['name'],
                'sort_order'    => $spec['sort_order'],
                'is_parade'     => $spec['session_type'] === 'morning' && $isParadeDay,
                'timein_start'  => self::timeOn($date, $window['timein_start']),
                'timein_end'    => self::timeOn($date, $window['timein_end']),
                'timeout_start' => self::timeOn($date, $window['timeout_start']),
                'timeout_end'   => self::timeOn($date, $window['timeout_end']),
            ]);
        }
    }

    /** Puts an 'H:i' time on a date; null/'' stays null (no time-out window). */
    protected static function timeOn(Carbon $date, ?string $time): ?Carbon
    {
        return $time ? $date->copy()->setTimeFromTimeString($time) : null;
    }

    /**
     * One period's four times from the submitted data. A period with no
     * time-in submitted falls back to the defaults (this is how the Flutter
     * client, which sends no times, keeps working). When time-in IS
     * submitted, a blank time-out means "no time-out for this session".
     */
    protected static function resolvePeriodWindow(array $data, string $period): array
    {
        if (empty($data["{$period}_timein_start"])) {
            return self::DEFAULT_WINDOWS[$period];
        }

        $window = [];
        foreach (self::WINDOW_FIELDS as $field) {
            $window[$field] = ($data["{$period}_{$field}"] ?? null) ?: null;
        }

        return $window;
    }

    /**
     * Which dates (Y-m-d) of the range are parade days. Explicit parade_dates
     * win; otherwise parade_mode (none/first/last/all); otherwise the legacy
     * has_parade flag (old Flutter builds) means "first day". Dates outside
     * the range are dropped.
     */
    public static function resolveParadeDates(array $input, string $start, string $end): array
    {
        $days = [];
        foreach (Carbon::parse($start)->toPeriod(Carbon::parse($end)) as $date) {
            $days[] = $date->toDateString();
        }

        if (empty($days)) {
            return [];
        }

        if (!empty($input['parade_dates'])) {
            return array_values(array_intersect($days, $input['parade_dates']));
        }

        $mode = $input['parade_mode'] ?? (!empty($input['has_parade']) ? 'first' : 'none');

        return match ($mode) {
            'first' => [$days[0]],
            'last'  => [$days[count($days) - 1]],
            'all'   => $days,
            default => [],
        };
    }

    /**
     * Programs grouped by department for the "required programs" picker.
     * The master list is config/academic_programs.php (the same file the
     * registration form uses). Any program on an approved student that isn't
     * in that file is kept under "Other" so nobody is left out.
     *
     * @return array<string, string[]>
     */
    public static function programGroups(): array
    {
        $groups = config('academic_programs', []);
        $listed = array_merge(...array_values($groups));

        $unlisted = User::students()->approved()
            ->whereNotNull('program')->where('program', '!=', '')
            ->distinct()->orderBy('program')->pluck('program')
            ->diff($listed)->values()->all();

        if (!empty($unlisted)) {
            $groups['Other'] = $unlisted;
        }

        return $groups;
    }

    /** Flat list of every selectable program (used to validate submitted programs). */
    public static function programOptions(): array
    {
        return array_merge(...array_values(self::programGroups()));
    }

    /**
     * Submitted program names reduced to known ones (exact match against the
     * picker's options), sorted. null = no restriction (every program).
     */
    public static function resolveTargetPrograms(array $input): ?array
    {
        $submitted = array_map(fn ($p) => trim((string) $p), (array) ($input['target_programs'] ?? []));
        $valid = array_values(array_intersect(self::programOptions(), $submitted));
        sort($valid);

        return $valid ?: null;
    }

    /** New target programs as ['value' => ?array] when the submission changes them, else null. */
    protected static function submittedPrograms(Event $event, array $input): ?array
    {
        if (empty($input['target_programs_submitted']) && !array_key_exists('target_programs', $input)) {
            return null;
        }

        $new = self::resolveTargetPrograms($input);
        $current = $event->target_programs ?: null;
        if (is_array($current)) {
            sort($current);
        }

        return $new === $current ? null : ['value' => $new];
    }

    /** True once any session of the event has already issued its fines. */
    protected static function finesIssuedFor(Event $event): bool
    {
        return EventSession::whereIn('event_day_id', $event->eventDays()->select('id'))
            ->where('fines_issued', true)
            ->exists();
    }

    /**
     * Applies edited required programs, time windows and parade dates to an
     * existing event in one go. Everything is checked before anything is
     * written, so a blocked change never leaves a half-applied edit.
     * Returns an error message when blocked, otherwise null.
     */
    public static function updateSchedule(Event $event, array $input): ?string
    {
        $newPrograms = self::submittedPrograms($event, $input);

        if ($newPrograms !== null && self::finesIssuedFor($event)) {
            return 'Fines were already issued for this event, so its required programs can no longer be changed.';
        }

        $newCustom = self::submittedCustomSessions($event, $input);
        if ($newCustom !== null && $event->attendance()->exists()) {
            return 'This event already has recorded attendance — its session times can no longer be changed.';
        }

        $error = self::applyScheduleChanges($event, $input);
        if ($error) {
            return $error;
        }

        if ($newCustom !== null) {
            self::applyCustomSessions($event, $newCustom);
        }

        if ($newPrograms !== null) {
            $event->update(['target_programs' => $newPrograms['value']]);
        }

        return null;
    }

    /**
     * The custom sessions of an event (read from its first day), keyed by
     * sort_order, as 'H:i' strings ('' = unset). Empty for full-day events.
     */
    public static function currentCustomSessions(Event $event): array
    {
        $event->loadMissing('eventDays.sessions');
        $firstDay = $event->eventDays->sortBy('date')->first();

        $rows = [];
        foreach ($firstDay?->sessions->where('session_type', 'custom')->sortBy('sort_order') ?? [] as $session) {
            $rows[$session->sort_order] = [
                'name'          => (string) $session->name,
                'timein_start'  => $session->timein_start?->format('H:i') ?? '',
                'timein_end'    => $session->timein_end?->format('H:i') ?? '',
                'timeout_start' => $session->timeout_start?->format('H:i') ?? '',
                'timeout_end'   => $session->timeout_end?->format('H:i') ?? '',
            ];
        }

        return $rows;
    }

    /** Edited custom sessions (keyed by sort_order) when they differ from what's saved, else null. */
    protected static function submittedCustomSessions(Event $event, array $input): ?array
    {
        if (empty($input['custom_sessions'])) {
            return null;
        }

        $current = self::currentCustomSessions($event);
        if (empty($current)) {
            return null; // not a custom-session event
        }

        $new = [];
        foreach ($current as $order => $saved) {
            $row = $input['custom_sessions'][$order] ?? null;
            if ($row === null) {
                $new[$order] = $saved;
                continue;
            }

            $new[$order] = [
                'name'          => trim((string) ($row['name'] ?? '')) ?: $saved['name'],
                'timein_start'  => (string) (($row['timein_start'] ?? '') ?: $saved['timein_start']),
                'timein_end'    => (string) (($row['timein_end'] ?? '') ?: $saved['timein_end']),
                'timeout_start' => (string) ($row['timeout_start'] ?? ''),
                'timeout_end'   => (string) ($row['timeout_end'] ?? ''),
            ];
        }

        return $new === $current ? null : $new;
    }

    /** Writes edited custom sessions onto the matching session (same sort_order) of every day. */
    protected static function applyCustomSessions(Event $event, array $rows): void
    {
        DB::transaction(function () use ($event, $rows) {
            $event->load('eventDays.sessions');

            foreach ($event->eventDays as $day) {
                foreach ($day->sessions as $session) {
                    if ($session->session_type !== 'custom' || !isset($rows[$session->sort_order])) {
                        continue;
                    }

                    $row = $rows[$session->sort_order];
                    $session->update([
                        'name'          => $row['name'],
                        'timein_start'  => self::timeOn($day->date, $row['timein_start'] ?: null),
                        'timein_end'    => self::timeOn($day->date, $row['timein_end'] ?: null),
                        'timeout_start' => self::timeOn($day->date, $row['timeout_start'] ?: null),
                        'timeout_end'   => self::timeOn($day->date, $row['timeout_end'] ?: null),
                    ]);
                }
            }
        });
    }

    /** Current windows read back from the first day's sessions, as flat 'H:i' strings ('' = unset). */
    public static function currentTimeWindow(Event $event): array
    {
        $event->loadMissing('eventDays.sessions');
        $firstDay = $event->eventDays->sortBy('date')->first();

        $window = [];
        foreach (self::PERIODS as $period) {
            $session = $firstDay?->sessions->firstWhere('session_type', $period);
            foreach (self::WINDOW_FIELDS as $field) {
                $window["{$period}_{$field}"] = $session?->{$field}?->format('H:i') ?? '';
            }
        }

        return $window;
    }

    /** Dates (Y-m-d, sorted) whose Morning session is flagged as parade. */
    public static function currentParadeDates(Event $event): array
    {
        $event->loadMissing('eventDays.sessions');

        return $event->eventDays
            ->sortBy('date')
            ->filter(fn (EventDay $day) => $day->sessions->contains(fn (EventSession $s) => $s->is_parade))
            ->map(fn (EventDay $day) => $day->date->toDateString())
            ->values()
            ->all();
    }

    /**
     * Applies edited time windows and/or parade dates to an existing event.
     * Only touches what actually changed, so saving unrelated details never
     * overwrites per-session edits. Returns an error message when the change
     * is blocked, otherwise null (including when nothing changed).
     *
     * Blocked once attendance exists (it would shift the windows records
     * were taken against); parade changes are also blocked once any session
     * has already issued fines (the amounts couldn't be re-priced).
     */
    protected static function applyScheduleChanges(Event $event, array $input): ?string
    {
        $windowKeys = array_keys(self::defaultTimeWindow());
        $windowProvided = !empty(array_intersect_key($input, array_flip($windowKeys)));
        $paradeProvided = array_key_exists('parade_dates', $input)
            || !empty($input['parade_dates_submitted'])
            || !empty($input['parade_mode']);

        if (!$windowProvided && !$paradeProvided) {
            return null;
        }

        $event->load('eventDays.sessions');

        $currentWindow = self::currentTimeWindow($event);
        $newWindow = $windowProvided ? self::submittedWindow($input, $currentWindow) : $currentWindow;

        $changedPeriods = array_values(array_filter(
            self::PERIODS,
            fn (string $p) => self::periodSlice($newWindow, $p) !== self::periodSlice($currentWindow, $p)
        ));

        $newParade = $paradeProvided
            ? self::resolveParadeDates($input, $event->date_start->toDateString(), $event->date_end->toDateString())
            : null;
        $paradeChanged = $newParade !== null && $newParade !== self::currentParadeDates($event);

        if (empty($changedPeriods) && !$paradeChanged) {
            return null;
        }

        if ($event->attendance()->exists()) {
            return 'This event already has recorded attendance — its time windows and parade dates can no longer be changed.';
        }

        $finesIssued = $event->eventDays
            ->flatMap(fn (EventDay $day) => $day->sessions)
            ->contains(fn (EventSession $s) => $s->fines_issued);
        if ($paradeChanged && $finesIssued) {
            return 'Fines were already issued for this event, so parade dates can no longer be changed.';
        }

        DB::transaction(function () use ($event, $newWindow, $changedPeriods, $paradeChanged, $newParade) {
            if (!empty($changedPeriods)) {
                self::applyWindow($event, $newWindow, $changedPeriods);
            }
            if ($paradeChanged) {
                self::applyParadeDates($event, $newParade);
            }
        });

        return null;
    }

    /** Submitted flat window; a period with no time-in submitted keeps its current values. */
    protected static function submittedWindow(array $input, array $current): array
    {
        $window = [];
        foreach (self::PERIODS as $period) {
            $keep = empty($input["{$period}_timein_start"]);
            foreach (self::WINDOW_FIELDS as $field) {
                $key = "{$period}_{$field}";
                $window[$key] = $keep ? $current[$key] : (string) ($input[$key] ?? '');
            }
        }

        return $window;
    }

    /** The four flat keys belonging to one period. */
    protected static function periodSlice(array $window, string $period): array
    {
        return array_filter($window, fn ($key) => str_starts_with($key, "{$period}_"), ARRAY_FILTER_USE_KEY);
    }

    /** Writes the given periods' times onto every day's matching session (keeps each session's own date). */
    protected static function applyWindow(Event $event, array $window, array $periods): void
    {
        foreach ($event->eventDays as $day) {
            foreach ($day->sessions as $session) {
                if (!in_array($session->session_type, $periods, true)) {
                    continue; // also skips legacy 'parade' sessions
                }

                $p = $session->session_type;
                $session->update([
                    'timein_start'  => self::timeOn($day->date, $window["{$p}_timein_start"] ?: null),
                    'timein_end'    => self::timeOn($day->date, $window["{$p}_timein_end"] ?: null),
                    'timeout_start' => self::timeOn($day->date, $window["{$p}_timeout_start"] ?: null),
                    'timeout_end'   => self::timeOn($day->date, $window["{$p}_timeout_end"] ?: null),
                ]);
            }
        }
    }

    /** Flags exactly the given dates' Morning sessions as parade; keeps events.has_parade and the fine rule in sync. */
    protected static function applyParadeDates(Event $event, array $dates): void
    {
        $hasLegacyParadeSession = false;

        foreach ($event->eventDays as $day) {
            $isParadeDay = in_array($day->date->toDateString(), $dates, true);

            foreach ($day->sessions as $session) {
                if ($session->session_type === 'parade') {
                    $hasLegacyParadeSession = true;
                }
                if ($session->session_type === 'morning') {
                    $session->update(['is_parade' => $isParadeDay]);
                }
            }
        }

        $event->update(['has_parade' => !empty($dates) || $hasLegacyParadeSession]);

        if (!empty($dates)) {
            EventFineRule::firstOrCreate(
                ['event_id' => $event->id, 'violation_type' => 'missed_parade'],
                ['amount' => 0]
            );
        }
    }

    /**
     * Shifts the event's whole date range (and every generated EventDay +
     * its sessions) by the same number of days, preserving the event's
     * original duration and each session's time-of-day. Used by both the
     * web calendar's drag-to-reschedule and the mobile "change start
     * date" action, so they can't drift apart.
     *
     * Caller is responsible for the attendance-already-recorded guard —
     * this method only does the date math, since the two callers return
     * different response shapes (redirect vs JSON) on that rejection.
     *
     * @return int the delta in days actually applied (0 if unchanged)
     */
    public static function reschedule(Event $event, string $newStartDate): int
    {
        $newStart = Carbon::parse($newStartDate)->startOfDay();
        $deltaDays = $event->date_start->copy()->startOfDay()->diffInDays($newStart, false);

        if ($deltaDays === 0) {
            return 0;
        }

        DB::transaction(function () use ($event, $deltaDays) {
            $event->update([
                'date_start' => $event->date_start->copy()->addDays($deltaDays),
                'date_end'   => $event->date_end->copy()->addDays($deltaDays),
            ]);

            foreach ($event->eventDays()->with('sessions')->get() as $day) {
                $day->update(['date' => $day->date->copy()->addDays($deltaDays)]);

                foreach ($day->sessions as $session) {
                    $session->update([
                        'timein_start'  => $session->timein_start?->copy()->addDays($deltaDays),
                        'timein_end'    => $session->timein_end?->copy()->addDays($deltaDays),
                        'timeout_start' => $session->timeout_start?->copy()->addDays($deltaDays),
                        'timeout_end'   => $session->timeout_end?->copy()->addDays($deltaDays),
                    ]);
                }
            }
        });

        return $deltaDays;
    }
}