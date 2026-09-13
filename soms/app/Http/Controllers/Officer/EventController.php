<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Organization;
use App\Services\EventSetupService;
use App\Services\NotificationService;
use App\Services\SafeImageUpload;
use App\Support\EventTypes;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        return view('officer.events.create', [
            'eventTypes' => EventTypes::forSelect(),
            'foundationDayActivities' => EventTypes::FOUNDATION_DAY_ACTIVITIES,
        ]);
    }

    /**
     * Also the target of the calendar's quick-create modal (dateClick on
     * an empty day) — the modal computes date_end from a "days" stepper
     * client-side and posts the same title/description/venue/type/
     * date_start/date_end/has_parade shape as the full create form.
     */
    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue'       => 'nullable|string|max:255',
            'type'        => 'required|' . EventTypes::validationRule(),
            'date_start'  => 'required|date',
            'date_end'    => 'required|date|after_or_equal:date_start',
            'has_parade'  => 'nullable|boolean',
            'image'       => 'nullable|file|image|mimes:jpeg,png,webp|max:2048',
            'color'       => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = SafeImageUpload::store($request->file('image'), 'r2', 'events');
        }
        unset($data['image']);

        $event = EventSetupService::create($data, $this->organizationId(), auth()->id());

        ActivityLog::record(auth()->id(), 'event_created', Event::class, $event->id, ['title' => $event->title]);

        // The calendar's quick-create modal sets source=calendar so officers
        // land back on the calendar and see the event appear immediately;
        // the full /officer/events/create form has no such field, so it
        // keeps going to the event's detail page to set fines/review sessions.
        if ($request->input('source') === 'calendar') {
            return redirect()->route('officer.calendar.index')->with('status', '"' . $event->title . '" created — set fine amounts from the event page when ready.');
        }

        return redirect()->route('officer.events.show', $event)->with('status', 'Event created. Set fine amounts and review session times below.');
    }

    public function show(Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $event->load(['eventDays.sessions.delegates.user', 'fineRules']);

        return view('officer.events.show', [
            'event' => $event,
            'eventTypes' => EventTypes::forSelect(),
        ]);
    }

    public function publish(Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $event->update(['is_published' => true]);
        ActivityLog::record(auth()->id(), 'event_published', Event::class, $event->id);

        NotificationService::broadcastToOrganization($event->organization_id, 'event_published', [
            'title' => $event->title,
        ]);

        return redirect()->route('officer.events.index')->with('status', 'Event published — all members notified.');
    }

    /**
     * Drag-and-drop reschedule from the calendar view — shifts the
     * event's whole date range (and every generated EventDay + its
     * sessions) by the same number of days, preserving the event's
     * original duration and each session's time-of-day. Called via
     * fetch(); FullCalendar reverts the dragged card if this responds
     * with anything but 2xx.
     *
     * Blocked once attendance has actually been recorded against any
     * day of the event — moving the dates at that point would detach
     * recorded attendance from the schedule it was taken against.
     * Reschedule before attendance starts, or edit the event manually
     * if it's already underway.
     */
    public function reschedule(Request $request, Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $data = $request->validate([
            'date_start' => 'required|date',
        ]);

        abort_if(
            $event->attendance()->exists(),
            422,
            'This event already has recorded attendance and can no longer be dragged to a new date. Edit it manually instead.'
        );

        $deltaDays = EventSetupService::reschedule($event, $data['date_start']);

        if ($deltaDays === 0) {
            return response()->json(['success' => true, 'message' => 'No change.']);
        }

        ActivityLog::record(auth()->id(), 'event_rescheduled', Event::class, $event->id, [
            'title'       => $event->title,
            'delta_days'  => $deltaDays,
            'date_start'  => $event->date_start->toDateString(),
            'date_end'    => $event->date_end->toDateString(),
        ]);

        if ($event->is_published) {
            NotificationService::broadcastToOrganization($event->organization_id, 'event_rescheduled', [
                'title' => $event->title,
                'date'  => $event->date_start->format('M j, Y'),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Event rescheduled.']);
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

    /**
     * Edit the event's own details (title/description/venue/type) — not
     * its dates, which move via reschedule() above and would otherwise
     * need to re-derive EventDays/sessions here too.
     */
    public function update(Request $request, Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue'       => 'nullable|string|max:255',
            'type'        => 'required|' . EventTypes::validationRule(),
            'image'       => 'nullable|file|image|mimes:jpeg,png,webp|max:2048',
            'color'       => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if ($request->hasFile('image')) {
            $oldPath = $event->image_path;
            $data['image_path'] = SafeImageUpload::store($request->file('image'), 'r2', 'events');
            if ($oldPath) {
                Storage::disk('r2')->delete($oldPath);
            }
        }
        unset($data['image']);

        $event->update($data);

        ActivityLog::record(auth()->id(), 'event_updated', Event::class, $event->id, $data);

        return back()->with('status', 'Event details updated.');
    }

    /**
     * Delete an event entirely — EventDays/Sessions/FineRules all
     * cascadeOnDelete at the DB level, so $event->delete() cleans up
     * everything. Blocked once attendance has actually been recorded,
     * same protective reasoning as reschedule() above: deleting at that
     * point would silently orphan real attendance/fine history rather
     * than just an empty schedule.
     */
    public function destroy(Event $event)
    {
        $this->authorizeManage();
        abort_unless($event->organization_id === $this->organizationId(), 404);

        if ($event->attendance()->exists()) {
            return back()->with('error', 'This event already has recorded attendance and can\'t be deleted — the history would be lost.');
        }

        $title = $event->title;
        ActivityLog::record(auth()->id(), 'event_deleted', Event::class, $event->id, ['title' => $title]);

        $event->delete();

        return redirect()->route('officer.events.index')->with('status', '"' . $title . '" deleted.');
    }
}
