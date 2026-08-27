<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Organization;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Event setup (Executive / Administrative officers) — see 05-Attendance-Fines.md Part A.
 *
 * Creating an event auto-generates event_days for the date range, and each
 * day auto-generates morning/afternoon (+ parade if has_parade) sessions.
 * Officers may edit individual session times and per-violation fine amounts
 * afterward. This is the prerequisite scaffolding the Attendance module
 * (AttendanceController) scans against.
 */
class EventController extends Controller
{
    protected function authorizeManage(): void
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_events'), 403);
    }

    protected function organizationId(): ?int
    {
        $user = auth()->user();

        return $user->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index()
    {
        $this->authorizeManage();

        $events = Event::where('organization_id', $this->organizationId())
            ->withCount('eventDays')
            ->orderByDesc('date_start')
            ->paginate(15);

        return view('officer.events.index', compact('events'));
    }

    public function create()
    {
        $this->authorizeManage();

        return view('officer.events.create');
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue'       => 'nullable|string|max:255',
            'type'        => 'required|in:foundation_day,other',
            'date_start'  => 'required|date',
            'date_end'    => 'required|date|after_or_equal:date_start',
            'has_parade'  => 'nullable|boolean',
        ]);

        $event = DB::transaction(function () use ($data) {
            $event = Event::create([
                'organization_id' => $this->organizationId(),
                'created_by'      => auth()->id(),
                'title'           => $data['title'],
                'description'     => $data['description'] ?? null,
                'venue'           => $data['venue'] ?? null,
                'type'            => $data['type'],
                'date_start'      => $data['date_start'],
                'date_end'        => $data['date_end'],
                'has_parade'      => (bool) ($data['has_parade'] ?? false),
            ]);

            $period = Carbon::parse($data['date_start'])->toPeriod(Carbon::parse($data['date_end']));

            foreach ($period as $date) {
                $day = EventDay::create([
                    'event_id' => $event->id,
                    'date'     => $date->toDateString(),
                ]);

                $this->createDefaultSessions($day, $event, $date);
            }

            // Default fine rule amounts (₱0) so the table exists — officer edits amounts next.
            $violations = ['missed_morning_timein', 'missed_morning_timeout', 'missed_afternoon_timein', 'missed_afternoon_timeout'];
            if ($event->has_parade) {
                $violations[] = 'missed_parade';
            }
            foreach ($violations as $violation) {
                EventFineRule::firstOrCreate(
                    ['event_id' => $event->id, 'violation_type' => $violation],
                    ['amount' => 0]
                );
            }

            return $event;
        });

        ActivityLog::record(auth()->id(), 'event_created', Event::class, $event->id, ['title' => $event->title]);

        return redirect()->route('officer.events.show', $event)->with('status', 'Event created. Set fine amounts and review session times below.');
    }

    protected function createDefaultSessions(EventDay $day, Event $event, Carbon $date): void
    {
        EventSession::create([
            'event_day_id'  => $day->id,
            'session_type'  => 'morning',
            'timein_start'  => $date->copy()->setTime(7, 0),
            'timein_end'    => $date->copy()->setTime(8, 0),
            'timeout_start' => $date->copy()->setTime(11, 30),
            'timeout_end'   => $date->copy()->setTime(12, 30),
        ]);

        EventSession::create([
            'event_day_id'  => $day->id,
            'session_type'  => 'afternoon',
            'timein_start'  => $date->copy()->setTime(13, 0),
            'timein_end'    => $date->copy()->setTime(14, 0),
            'timeout_start' => $date->copy()->setTime(17, 0),
            'timeout_end'   => $date->copy()->setTime(18, 0),
        ]);

        if ($event->has_parade) {
            EventSession::create([
                'event_day_id' => $day->id,
                'session_type' => 'parade',
                'timein_start' => $date->copy()->setTime(6, 0),
                'timein_end'   => $date->copy()->setTime(6, 45),
            ]);
        }
    }

    public function show(Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $event->load(['eventDays.sessions.delegates.user', 'fineRules']);

        return view('officer.events.show', compact('event'));
    }

    public function publish(Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $event->update(['is_published' => true]);
        ActivityLog::record(auth()->id(), 'event_published', Event::class, $event->id);

        return back()->with('status', 'Event published — visible to students now.');
    }

    public function updateSession(Request $request, EventSession $session)
    {
        $this->authorizeManage();
        abort_unless($session->eventDay->event->organization_id === $this->organizationId(), 404);

        $rules = [
            'timein_start' => 'required|date',
            'timein_end'   => 'required|date|after:timein_start',
        ];
        if ($session->session_type !== 'parade') {
            $rules['timeout_start'] = 'nullable|date';
            $rules['timeout_end']   = 'nullable|date|after:timeout_start';
        }

        $data = $request->validate($rules);
        $session->update($data);

        ActivityLog::record(auth()->id(), 'session_times_updated', EventSession::class, $session->id, $data);

        return back()->with('status', ucfirst($session->session_type) . ' session times updated.');
    }

    public function updateFineRules(Request $request, Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $data = $request->validate([
            'amounts'   => 'required|array',
            'amounts.*' => 'required|numeric|min:0',
        ]);

        foreach ($data['amounts'] as $violationType => $amount) {
            EventFineRule::where('event_id', $event->id)
                ->where('violation_type', $violationType)
                ->update(['amount' => $amount]);
        }

        ActivityLog::record(auth()->id(), 'fine_rules_updated', Event::class, $event->id, $data['amounts']);

        return back()->with('status', 'Fine amounts updated.');
    }
}
