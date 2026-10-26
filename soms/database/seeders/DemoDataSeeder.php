<?php
// database/seeders/DemoDataSeeder.php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AuditFinding;
use App\Models\AttendanceDelegate;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\OfficerPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

/**
 * TEMPORARY demo data for trying out every role and screen.
 *
 *   php artisan db:seed --class=DemoDataSeeder     # add the demo data
 *   php artisan db:seed --class=DemoDataCleanupSeeder   # remove it again
 *
 * Builds on DevSeeder (admin, 6 officers, 20 students, 2 upcoming events) and
 * adds: officer permissions, pending/forced-password accounts, two PAST
 * events whose absentees already have unpaid / paid / waived fines, a LIVE
 * event happening right now (with scans and offline scans waiting for
 * review), and more upcoming events (multi-day, program-targeted, custom
 * sessions, a draft). Everything demo-only is titled "[DEMO] ..." or flagged
 * so the cleanup seeder can find it. Safe to run more than once.
 */
class DemoDataSeeder extends Seeder
{
    public const DEMO_PREFIX = '[DEMO] ';

    private Organization $org;
    private User $admin;
    private User $treasurer;

    /** @var array<string, User> officer position title => user */
    private array $officers = [];

    /** @var Collection<int, User> S0000000001..S0000000020, in order */
    private Collection $students;

    /** @var array<string, int> fine ids of the problems planted for the Auditor */
    private array $planted = [];

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->error('DemoDataSeeder must not run in production. Aborting.');
            return;
        }

        Model::unguard();

        // Base accounts + two upcoming events (idempotent).
        $this->call(DevSeeder::class);

        $this->loadBaseUsers();
        $this->grantOfficerPermissions();
        $this->assignRealPrograms();
        $this->backdateRegistrations();
        $this->seedExtraAccounts();
        $this->seedPastEventsWithFines();
        $this->seedAuditScenarios();
        $this->seedLiveEvent();
        $this->seedUpcomingEvents();
        $this->seedAnnouncements();

        ActivityLog::record($this->admin->id, 'demo_data_seeded', Organization::class, $this->org->id, ['seeded' => true]);

        Model::reguard();
        $this->printSummary();
    }

    // ------------------------------------------------------------------
    // Accounts
    // ------------------------------------------------------------------

    /** Loads the DevSeeder users this seeder builds on. */
    private function loadBaseUsers(): void
    {
        $this->org = Organization::where('name', 'Student Government Organization')->firstOrFail();
        $this->admin = User::where('student_id', 'A0000000000')->firstOrFail();

        $positions = OfficerPosition::with('user')
            ->where('organization_id', $this->org->id)
            ->where('is_active', true)
            ->get();

        foreach ($positions as $position) {
            $this->officers[$position->position_title] = $position->user;
        }

        $this->treasurer = $this->officers['Treasurer'];

        $this->students = User::whereBetween('student_id', ['S0000000001', 'S0000000020'])
            ->orderBy('student_id')
            ->get()
            ->values();
    }

    /**
     * Gives each demo officer the recommended access for their position
     * (OfficerPermission::PRESETS). Only fills in officers that have none,
     * so it never overwrites what Admin configured. The Auditor alone also
     * gets any audit permissions it is missing, for demo databases seeded
     * before those permissions existed.
     */
    private function grantOfficerPermissions(): void
    {
        foreach (OfficerPermission::PRESETS as $title => $permissions) {
            OfficerPosition::where('organization_id', $this->org->id)
                ->where('position_title', $title)
                ->where('is_active', true)
                ->get()
                ->each(function (OfficerPosition $position) use ($title, $permissions) {
                    if (empty($position->permissions)) {
                        $position->update(['permissions' => $permissions]);
                    } elseif ($title === 'Auditor') {
                        $position->update(['permissions' => array_values(array_unique(array_merge($position->permissions, $permissions)))]);
                    }
                });
        }
    }

    /**
     * DevSeeder gives every student the placeholder program "Sample Program",
     * which would never match a program-targeted event. Spread the students
     * over the real department/program pairs from config/academic_programs.php.
     */
    private function assignRealPrograms(): void
    {
        $pairs = $this->programPairs();

        $this->students->each(function (User $student, int $index) use ($pairs) {
            if ($student->program !== 'Sample Program') {
                return;
            }

            [$department, $program] = $pairs[$index % count($pairs)];
            $student->update(['department' => $department, 'program' => $program]);
        });
    }

    /**
     * DevSeeder creates the demo students "now", but the past events are weeks
     * old. The Auditor's missing-fine check only expects students who were
     * registered before a session closed, so make the demo students look like
     * they signed up two months ago, as real ones would have.
     */
    private function backdateRegistrations(): void
    {
        User::whereIn('id', $this->students->pluck('id'))
            ->where('created_at', '>', now()->subDays(30))
            ->update(['created_at' => now()->subDays(60)]);
    }

    /** @return array<int, array{0: string, 1: string}> [department, program] pairs */
    private function programPairs(): array
    {
        $pairs = [];
        foreach (config('academic_programs') as $department => $programs) {
            foreach ($programs as $program) {
                $pairs[] = [$department, $program];
            }
        }

        return $pairs;
    }

    /** Accounts for the admin approval queue and the forced-password-change flow. */
    private function seedExtraAccounts(): void
    {
        [$department, $program] = $this->programPairs()[0];

        $extras = [
            ['S0000000101', 'Demo Pending Student A', false, false],
            ['S0000000102', 'Demo Pending Student B', false, false],
            ['S0000000103', 'Demo Must Change Password', true, true],
        ];

        foreach ($extras as [$studentId, $name, $approved, $mustChangePassword]) {
            User::firstOrCreate(
                ['student_id' => $studentId],
                [
                    'name'                 => $name,
                    'email'                => strtolower($studentId) . '@dev.local',
                    'password'             => Hash::make('123456'),
                    'role'                 => 'student',
                    'department'           => $department,
                    'program'              => $program,
                    'level'                => '1st Year',
                    'is_approved'          => $approved,
                    'must_change_password' => $mustChangePassword,
                    'qr_generated_at'      => $approved ? now() : null,
                ]
            );
        }
    }

    // ------------------------------------------------------------------
    // Past events + fines (unpaid / paid / waived)
    // ------------------------------------------------------------------

    /**
     * Two finished events. Each student gets one of four attendance
     * profiles, which produce fines in every status:
     *   1 -> perfect attendance, no fines
     *   2 -> one PAID fine
     *   3 -> two UNPAID fines + one WAIVED fine
     *   0 -> one UNPAID fine (the ₱250 parade) + one PAID + one WAIVED
     */
    private function seedPastEventsWithFines(): void
    {
        // Already seeded. Re-running must not rebuild the fines: some were
        // deliberately altered or removed as audit scenarios (seedAuditScenarios),
        // and recreating them would silently undo that.
        if ($this->demoFines()->exists()) {
            return;
        }

        $assembly = $this->seedFullDayEvent(
            title: self::DEMO_PREFIX . 'Student Assembly (last week)',
            type: 'student_assembly',
            date: now()->subDays(8)->startOfDay(),
            paradeMorning: false,
            finesIssued: true,
            fineRules: [
                'missed_morning_timein'    => 50,
                'missed_morning_timeout'   => 30,
                'missed_afternoon_timein'  => 50,
                'missed_afternoon_timeout' => 30,
            ],
        );

        $parade = $this->seedFullDayEvent(
            title: self::DEMO_PREFIX . 'Foundation Day Parade (2 weeks ago)',
            type: 'foundation_day',
            date: now()->subDays(15)->startOfDay(),
            paradeMorning: true,
            finesIssued: true,
            fineRules: [
                'missed_parade'            => 250,
                'missed_morning_timeout'   => 25,
                'missed_afternoon_timein'  => 50,
                'missed_afternoon_timeout' => 25,
            ],
        );

        $events = ['A' => $assembly, 'B' => $parade];

        // profile => [ "event.session.scan" => final fine status ]
        $missedByProfile = [
            1 => [],
            2 => ['A.morning.time_in' => 'paid'],
            3 => [
                'A.afternoon.time_in'  => 'unpaid',
                'A.afternoon.time_out' => 'unpaid',
                'B.afternoon.time_in'  => 'waived',
            ],
            0 => [
                'B.morning.time_in'    => 'unpaid',
                'A.morning.time_out'   => 'paid',
                'A.afternoon.time_out' => 'waived',
            ],
        ];

        foreach ($this->students as $index => $student) {
            $missed = $missedByProfile[($index + 1) % 4];

            foreach ($events as $eventKey => $eventData) {
                foreach ($eventData['sessions'] as $sessionKey => $session) {
                    foreach (['time_in', 'time_out'] as $scanType) {
                        $key = "{$eventKey}.{$sessionKey}.{$scanType}";

                        if (array_key_exists($key, $missed)) {
                            $this->issueFine($eventData['event'], $session, $student, $scanType, $eventData['rules'], $missed[$key]);
                        } else {
                            $this->recordScan($eventData['event'], $session, $student, $scanType);
                        }
                    }
                }
            }
        }
    }

    /**
     * One event, one day, Morning + Afternoon sessions (each with a time-in
     * and time-out window). With $paradeMorning the Morning session is the
     * parade check-in, so its missed time-in uses the higher parade fine.
     *
     * @param  array<string, int> $fineRules violation_type => amount
     * @return array{event: Event, sessions: array<string, EventSession>, rules: array<string, int>}
     */
    private function seedFullDayEvent(
        string $title,
        string $type,
        Carbon $date,
        bool $paradeMorning,
        bool $finesIssued,
        array $fineRules,
    ): array {
        [$event, $day] = $this->makeEventDay($title, $type, $date, hasParade: $paradeMorning);
        $this->setFineRules($event, $fineRules);

        $morning = $this->makeSession($day, 1, 'morning', null, $date, '07:00', '08:00', '11:30', '12:00', $paradeMorning, $finesIssued);
        $afternoon = $this->makeSession($day, 2, 'afternoon', null, $date, '13:00', '13:30', '17:00', '17:30', false, $finesIssued);

        return [
            'event'    => $event,
            'sessions' => ['morning' => $morning, 'afternoon' => $afternoon],
            'rules'    => $fineRules,
        ];
    }

    // ------------------------------------------------------------------
    // Audit scenarios (so the Auditor has something to find)
    // ------------------------------------------------------------------

    /**
     * Gives every paid fine a receipt number, then plants one of each kind
     * of problem the Auditor's checks look for. Runs once: skipped when any
     * fine already has a receipt number.
     */
    private function seedAuditScenarios(): void
    {
        if (Fine::whereNotNull('receipt_number')->exists()) {
            return;
        }

        $paid = $this->demoFines()->where('status', 'paid')->orderBy('id')->get();

        foreach ($paid as $index => $fine) {
            $fine->forceFill(['receipt_number' => sprintf('OR-2026-%04d', $index + 1)])->save();
        }

        if ($paid->count() >= 3) {
            // 1. Paid, but no receipt number was recorded.
            $paid[0]->forceFill(['receipt_number' => null])->save();
            $this->planted['no_receipt'] = $paid[0]->id;

            // 2. The same receipt recorded for two different students.
            $paid[2]->forceFill(['receipt_number' => $paid[1]->receipt_number])->save();
            $this->planted['duplicate_receipt'] = $paid[2]->id;
        }

        // 3. A waiver with a one-word reason.
        if ($waived = $this->demoFines()->where('status', 'waived')->orderBy('id')->first()) {
            $waived->forceFill(['reason' => 'ok'])->save();
            $this->planted['weak_waiver'] = $waived->id;
        }

        // 4. A fine whose amount no longer matches the fine rule (₱45 vs ₱50).
        $this->demoFines()->where('status', 'unpaid')->where('violation_type', 'missed_afternoon_timein')->orderBy('id')->first()
            ?->forceFill(['amount' => 45])->save();

        // 5. A fine that should exist but doesn't (the student has no attendance either).
        $this->demoFines()->where('status', 'unpaid')->where('violation_type', 'missed_afternoon_timeout')->orderBy('id')->first()
            ?->delete();

        // 6. A student fined, then marked present by manual override afterwards.
        if ($fine = $this->demoFines()->where('status', 'paid')->where('violation_type', 'missed_morning_timeout')->orderBy('id')->first()) {
            EventAttendance::updateOrCreate(
                ['event_session_id' => $fine->event_session_id, 'user_id' => $fine->user_id, 'scan_type' => 'time_out'],
                [
                    'event_id'           => $fine->event_id,
                    'event_day_id'       => $fine->event_day_id,
                    'scanned_at'         => $fine->issued_at->copy()->addDay(),
                    'marked_by'          => ($this->officers['Secretary'] ?? $this->admin)->id,
                    'status'             => 'present',
                    'is_manual_override' => true,
                    'override_reason'    => 'Scanner battery died; verified by the officer on duty.',
                ]
            );
        }

        // 7. A flagged scan nobody has reviewed for days, and 8. one that was approved.
        $stale = $this->paradeTimeIn($this->students[4]);
        $stale?->forceFill([
            'status'                    => 'flagged_for_review',
            'scanned_at'                => now()->subDays(5),
            'device_scanned_at'         => now()->subDays(5)->subMinutes(30),
            'synced_from_offline_queue' => true,
        ])->save();

        $approved = $this->paradeTimeIn($this->students[8]);
        $approved?->forceFill([
            'device_scanned_at'         => $approved->scanned_at->copy()->subMinutes(25),
            'synced_from_offline_queue' => true,
            'reviewed_by'               => ($this->officers['Secretary'] ?? $this->admin)->id,
            'reviewed_at'               => now()->subDays(3),
            'review_decision'           => 'approved',
        ])->save();

        $this->seedAuditFindings();
    }

    /** Sample findings in each state, so the findings screens aren't empty. */
    private function seedAuditFindings(): void
    {
        $auditor = $this->officers['Auditor'] ?? null;
        if (!$auditor) {
            return;
        }

        $findings = [
            [
                'title' => self::DEMO_PREFIX . 'Receipt reused for two students',
                'issue' => 'The same receipt number is recorded against fines of two different students.',
                'evidence' => 'Compare the two fines in Fines & Payments; both show the same OR number.',
                'severity' => 'high', 'status' => 'open',
                'check_type' => 'duplicate_receipt', 'reference_id' => $this->planted['duplicate_receipt'] ?? null,
            ],
            [
                'title' => self::DEMO_PREFIX . 'Waiver with a one-word reason',
                'issue' => 'A fine was waived with the reason "ok", which does not show who approved it or why.',
                'evidence' => null,
                'severity' => 'medium', 'status' => 'acknowledged',
                'check_type' => 'weak_waiver_reason', 'reference_id' => $this->planted['weak_waiver'] ?? null,
            ],
            [
                'title' => self::DEMO_PREFIX . 'Paid fine with no receipt number',
                'issue' => 'A fine was marked paid but no receipt number was recorded.',
                'evidence' => 'Recorded before receipt numbers were captured.',
                'severity' => 'low', 'status' => 'resolved',
                'check_type' => 'paid_without_receipt', 'reference_id' => $this->planted['no_receipt'] ?? null,
                'resolution_note' => 'Receipt book checked; the entry was a legacy payment. Receipts are now recorded on every payment.',
            ],
        ];

        foreach ($findings as $row) {
            $resolved = $row['status'] === 'resolved';

            AuditFinding::firstOrCreate(
                ['organization_id' => $this->org->id, 'title' => $row['title']],
                [
                    'reference_type'  => 'fine',
                    'reference_id'    => $row['reference_id'],
                    'check_type'      => $row['check_type'],
                    'issue'           => $row['issue'],
                    'evidence'        => $row['evidence'],
                    'severity'        => $row['severity'],
                    'status'          => $row['status'],
                    'reported_by'     => $auditor->id,
                    'reported_at'     => now()->subDays(4),
                    'resolution_note' => $row['resolution_note'] ?? null,
                    'resolved_by'     => $resolved ? $this->admin->id : null,
                    'resolved_at'     => $resolved ? now()->subDays(1) : null,
                ]
            );
        }
    }

    /** Fines that belong to the demo events only. */
    private function demoFines()
    {
        return Fine::whereHas('event', fn ($q) => $q->where('title', 'like', self::DEMO_PREFIX . '%'));
    }

    /** A student's latest time-in scan at the parade event (its afternoon session). */
    private function paradeTimeIn(User $student): ?EventAttendance
    {
        return EventAttendance::where('user_id', $student->id)
            ->where('scan_type', 'time_in')
            ->whereHas('event', fn ($q) => $q->where('title', 'like', self::DEMO_PREFIX . '%Parade%'))
            ->orderByDesc('id')
            ->first();
    }

    // ------------------------------------------------------------------
    // Live event (happening right now)
    // ------------------------------------------------------------------

    /**
     * An event whose time-in window is open right now, so scanning can be
     * tried immediately. Refreshed on every run so it stays "live". Includes
     * scans already taken, three offline scans flagged for review (clock
     * drift) and an assigned attendance delegate.
     */
    private function seedLiveEvent(): void
    {
        $now = now();

        [$event, $day] = $this->makeEventDay(self::DEMO_PREFIX . 'Live Scan Test (today)', 'other', $now->copy()->startOfDay());
        $this->setFineRules($event, ['missed_morning_timein' => 40, 'missed_morning_timeout' => 30]);

        $session = EventSession::updateOrCreate(
            ['event_day_id' => $day->id, 'sort_order' => 1],
            [
                'session_type'  => 'morning',
                'name'          => null,
                'is_parade'     => false,
                'timein_start'  => $now->copy()->subMinutes(30),
                'timein_end'    => $now->copy()->addHours(2),
                'timeout_start' => $now->copy()->addHours(2)->addMinutes(30),
                'timeout_end'   => $now->copy()->addHours(3),
                'fines_issued'  => false,
            ]
        );

        // First 8 students already scanned in normally.
        foreach ($this->students->take(8) as $student) {
            $this->recordScan($event, $session, $student, 'time_in', scannedAt: $now->copy()->subMinutes(20));
        }

        // Students 9-11 were scanned offline on a phone with a wrong clock:
        // held for review, not fined until an officer rejects the scan.
        foreach ($this->students->slice(8, 3) as $student) {
            $this->recordScan(
                $event,
                $session,
                $student,
                'time_in',
                status: 'flagged_for_review',
                scannedAt: $now->copy()->subMinutes(5),
                offlineDeviceTime: $now->copy()->subMinutes(30),
            );
        }

        // The Auditor has no attendance permission but may scan this session.
        if (isset($this->officers['Auditor'])) {
            AttendanceDelegate::firstOrCreate(
                ['event_session_id' => $session->id, 'user_id' => $this->officers['Auditor']->id],
                ['assigned_by' => $this->officers['Secretary']->id ?? $this->admin->id]
            );
        }
    }

    // ------------------------------------------------------------------
    // Upcoming events
    // ------------------------------------------------------------------

    private function seedUpcomingEvents(): void
    {
        // 1. Plain full-day event.
        $date = now()->addDays(3)->startOfDay();
        [$event, $day] = $this->makeEventDay(self::DEMO_PREFIX . 'General Assembly (in 3 days)', 'student_assembly', $date, notify: true);
        $this->setFineRules($event, ['missed_morning_timein' => 50, 'missed_morning_timeout' => 30, 'missed_afternoon_timein' => 50, 'missed_afternoon_timeout' => 30]);
        $this->makeSession($day, 1, 'morning', null, $date, '07:00', '08:00', '11:30', '12:00');
        $this->makeSession($day, 2, 'afternoon', null, $date, '13:00', '13:30', '17:00', '17:30');

        // 2. Three-day Foundation Week; the parade is on day 2 only.
        $start = now()->addDays(7)->startOfDay();
        [$week] = $this->makeEventDay(self::DEMO_PREFIX . 'Foundation Week (3 days)', 'foundation_day', $start, hasParade: true, endDate: $start->copy()->addDays(2));
        $this->setFineRules($week, ['missed_parade' => 250, 'missed_morning_timein' => 50, 'missed_morning_timeout' => 25, 'missed_afternoon_timein' => 50, 'missed_afternoon_timeout' => 25]);
        foreach (range(0, 2) as $offset) {
            $dayDate = $start->copy()->addDays($offset);
            $weekDay = EventDay::firstOrCreate(['event_id' => $week->id, 'date' => $dayDate->toDateString()], ['label' => 'Day ' . ($offset + 1)]);
            $this->makeSession($weekDay, 1, 'morning', null, $dayDate, '07:00', '08:00', '11:30', '12:00', isParade: $offset === 1);
            $this->makeSession($weekDay, 2, 'afternoon', null, $dayDate, '13:00', '13:30', '17:00', '17:30');
        }

        // 3. Targeted at one program: other students are not fined and can't scan.
        $date = now()->addDays(5)->startOfDay();
        $itProgram = collect($this->programPairs())->pluck(1)->first(fn ($p) => str_contains($p, 'Information Technology')) ?? $this->programPairs()[0][1];
        [$event, $day] = $this->makeEventDay(self::DEMO_PREFIX . 'IT Program Orientation (one program only)', 'academic_activities', $date, targetPrograms: [$itProgram]);
        $this->setFineRules($event, ['missed_custom_timein' => 25]);
        $this->makeSession($day, 1, 'custom', 'Orientation', $date, '09:00', '09:30');

        // 4. "Multiple activities": three custom sessions in one day.
        $date = now()->addDays(12)->startOfDay();
        [$event, $day] = $this->makeEventDay(self::DEMO_PREFIX . 'Leadership Training (multiple activities)', 'leadership_recognition', $date);
        $this->setFineRules($event, ['missed_custom_timein' => 25, 'missed_custom_timeout' => 15]);
        $this->makeSession($day, 1, 'custom', 'Registration', $date, '08:00', '08:30');
        $this->makeSession($day, 2, 'custom', 'Workshop', $date, '10:00', '10:30', '11:30', '12:00');
        $this->makeSession($day, 3, 'custom', 'Closing Program', $date, '15:00', '15:30');

        // 5. A draft nobody can see yet (not published, so never fined).
        $date = now()->addDays(14)->startOfDay();
        [$event, $day] = $this->makeEventDay(self::DEMO_PREFIX . 'Draft Event (unpublished)', 'other', $date, published: false);
        $this->setFineRules($event, ['missed_morning_timein' => 50]);
        $this->makeSession($day, 1, 'morning', null, $date, '07:00', '08:00');
    }

    // ------------------------------------------------------------------
    // Announcements
    // ------------------------------------------------------------------

    private function seedAnnouncements(): void
    {
        $publisher = $this->officers['Public Relations Officer'] ?? $this->admin;

        $announcements = [
            [self::DEMO_PREFIX . 'Welcome back, students!', 'Classes resume Monday. Check the calendar for upcoming SGO events.', true],
            [self::DEMO_PREFIX . 'Fine payment reminder', 'Unpaid fines can be settled with the Treasurer during office hours.', true],
            [self::DEMO_PREFIX . 'Draft: Intramurals schedule', 'Draft only — not yet visible to students.', false],
        ];

        foreach ($announcements as [$title, $body, $published]) {
            $announcement = Announcement::firstOrCreate(
                ['organization_id' => $this->org->id, 'title' => $title],
                ['created_by' => $publisher->id, 'body' => $body, 'is_published' => $published]
            );

            // Notify members only the first time a published announcement is created.
            if ($announcement->wasRecentlyCreated && $published) {
                NotificationService::broadcastToOrganization($this->org->id, 'announcement_published', ['announcement_id' => $announcement->id, 'demo' => true]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    /**
     * Creates (or finds) an event and its first day.
     *
     * @param  array<int, string>|null $targetPrograms null = all programs
     * @return array{0: Event, 1: EventDay}
     */
    private function makeEventDay(
        string $title,
        string $type,
        Carbon $date,
        bool $hasParade = false,
        bool $published = true,
        ?array $targetPrograms = null,
        ?Carbon $endDate = null,
        bool $notify = false,
    ): array {
        $event = Event::firstOrCreate(
            ['organization_id' => $this->org->id, 'title' => $title],
            [
                'created_by'      => ($this->officers['President'] ?? $this->admin)->id,
                'description'     => $title . ' — temporary demo event.',
                'venue'           => 'Main Campus Grounds',
                'type'            => $type,
                'date_start'      => $date->toDateString(),
                'date_end'        => ($endDate ?? $date)->toDateString(),
                'has_parade'      => $hasParade,
                'target_programs' => $targetPrograms,
                'is_published'    => $published,
            ]
        );

        if ($notify && $event->wasRecentlyCreated) {
            NotificationService::broadcastToOrganization($this->org->id, 'event_published', ['title' => $title, 'demo' => true]);
        }

        $day = EventDay::firstOrCreate(['event_id' => $event->id, 'date' => $date->toDateString()], ['label' => 'Day 1']);

        return [$event, $day];
    }

    /** @param array<string, int> $rules violation_type => amount */
    private function setFineRules(Event $event, array $rules): void
    {
        foreach ($rules as $violationType => $amount) {
            EventFineRule::firstOrCreate(
                ['event_id' => $event->id, 'violation_type' => $violationType],
                ['amount' => $amount]
            );
        }
    }

    /**
     * One session on a given date. $inStart/$inEnd are the time-in window,
     * $outStart/$outEnd the optional time-out window ("HH:MM" strings).
     * Keyed by (day, sort_order), which is what the database makes unique.
     */
    private function makeSession(
        EventDay $day,
        int $sortOrder,
        string $type,
        ?string $name,
        Carbon $date,
        string $inStart,
        string $inEnd,
        ?string $outStart = null,
        ?string $outEnd = null,
        bool $isParade = false,
        bool $finesIssued = false,
    ): EventSession {
        $at = fn (?string $time) => $time ? $date->copy()->setTimeFromTimeString($time) : null;

        return EventSession::updateOrCreate(
            ['event_day_id' => $day->id, 'sort_order' => $sortOrder],
            [
                'session_type'  => $type,
                'name'          => $name,
                'is_parade'     => $isParade,
                'timein_start'  => $at($inStart),
                'timein_end'    => $at($inEnd),
                'timeout_start' => $at($outStart),
                'timeout_end'   => $at($outEnd),
                'fines_issued'  => $finesIssued,
            ]
        );
    }

    /** Records a scan for a student; safe to call twice (unique per session/user/scan type). */
    private function recordScan(
        Event $event,
        EventSession $session,
        User $student,
        string $scanType,
        string $status = 'present',
        ?Carbon $scannedAt = null,
        ?Carbon $offlineDeviceTime = null,
    ): void {
        $windowStart = $scanType === 'time_out' ? $session->timeout_start : $session->timein_start;

        EventAttendance::firstOrCreate(
            ['event_session_id' => $session->id, 'user_id' => $student->id, 'scan_type' => $scanType],
            [
                'event_id'                  => $event->id,
                'event_day_id'              => $session->event_day_id,
                'scanned_at'                => $scannedAt ?? $windowStart->copy()->addMinutes(10),
                'marked_by'                 => ($this->officers['Secretary'] ?? $this->admin)->id,
                'status'                    => $status,
                'device_scanned_at'         => $offlineDeviceTime,
                'synced_from_offline_queue' => $offlineDeviceTime !== null,
            ]
        );
    }

    /**
     * Issues a fine the way IssueSessionFinesJob would, then settles it to
     * $finalStatus ('unpaid' | 'paid' | 'waived') the way the Treasurer's
     * Mark paid / Waive buttons do. Notifications are created once, on first run.
     *
     * @param array<string, int> $rules violation_type => amount
     */
    private function issueFine(Event $event, EventSession $session, User $student, string $scanType, array $rules, string $finalStatus): void
    {
        $violationType = $this->violationType($session, $scanType);

        $fine = Fine::firstOrCreate(
            ['user_id' => $student->id, 'event_session_id' => $session->id, 'violation_type' => $violationType],
            [
                'event_id'     => $event->id,
                'event_day_id' => $session->event_day_id,
                'reason'       => 'Missed ' . str_replace('_', ' ', substr($violationType, strlen('missed_'))),
                'amount'       => $rules[$violationType],
                'issued_at'    => $session->timein_end,
                'status'       => 'unpaid',
            ]
        );

        if (!$fine->wasRecentlyCreated) {
            return;
        }

        NotificationService::send($student->id, 'fine_issued', ['fine_id' => $fine->id, 'violation_type' => $violationType, 'amount' => (float) $fine->amount, 'demo' => true]);

        if ($finalStatus === 'unpaid') {
            return;
        }

        $reason = $finalStatus === 'paid'
            ? 'Paid in cash at the Treasurer\'s office (demo data)'
            : 'Excused — school-sanctioned activity (demo data)';

        $fine->forceFill([
            'status'     => $finalStatus,
            'cleared_by' => $this->treasurer->id,
            'cleared_at' => $session->timein_end->copy()->addDays(2),
            'reason'     => $reason,
        ])->save();

        NotificationService::send($student->id, $finalStatus === 'paid' ? 'fine_cleared' : 'fine_waived', ['fine_id' => $fine->id, 'reason' => $reason, 'demo' => true]);
    }

    /** Same violation_type naming IssueSessionFinesJob uses. */
    private function violationType(EventSession $session, string $scanType): string
    {
        if ($scanType === 'time_out') {
            return "missed_{$session->session_type}_timeout";
        }

        $isParadeCheckIn = $session->session_type === 'parade' || $session->is_parade;

        return $isParadeCheckIn ? 'missed_parade' : "missed_{$session->session_type}_timein";
    }

    // ------------------------------------------------------------------

    private function printSummary(): void
    {
        $fines = Fine::selectRaw('status, count(*) as total, sum(amount) as amount')->groupBy('status')->get()->keyBy('status');
        $line = fn (string $status) => sprintf('%d (₱%s)', $fines[$status]->total ?? 0, number_format((float) ($fines[$status]->amount ?? 0), 2));

        $this->command->info('DemoDataSeeder complete.');
        $this->command->info('Fines — unpaid: ' . $line('unpaid') . ' | paid: ' . $line('paid') . ' | waived: ' . $line('waived'));
        $this->command->info('Admin    A0000000000 / password');
        $this->command->info('Officers O0000000001..06 (President, VP, Secretary, Treasurer, Auditor, PRO) / 123456');
        $this->command->info('Students S0000000001..20 / 123456 (S...101 and S...102 are pending approval; S...103 must change password)');
        $this->command->info('Remove everything demo-only with: php artisan db:seed --class=DemoDataCleanupSeeder');
    }
}
