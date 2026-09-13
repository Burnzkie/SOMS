<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EventSetupService;
use App\Services\SafeImageUpload;
use App\Support\EventTypes;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Event listing + quick-create for the Flutter officer app — the
 * calendar's tap-a-day quick-create sheet and the scan screen's session
 * picker. store() mirrors Officer\EventController::store() (both call
 * EventSetupService) so mobile-created events go through the same
 * day/session/fine-rule generation as web-created ones.
 */
class EventController extends Controller
{
    protected function organizationId(Request $request): ?int
    {
        return $request->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index(Request $request)
    {
        $events = Event::where('organization_id', $this->organizationId($request))
            ->with('eventDays.sessions')
            ->orderByDesc('date_start')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $events]);
    }

    public function show(Request $request, Event $event)
    {
        abort_unless($event->organization_id === $this->organizationId($request), 404);
        $event->load('eventDays.sessions', 'fineRules');

        return response()->json(['success' => true, 'data' => $event]);
    }

    public function store(Request $request)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_events'), 403);

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

        $event = EventSetupService::create($data, $this->organizationId($request), $request->user()->id);

        ActivityLog::record($request->user()->id, 'event_created', Event::class, $event->id, ['title' => $event->title]);

        $event->load('eventDays.sessions', 'fineRules');

        return response()->json(['success' => true, 'data' => $event], 201);
    }

    /**
     * Edit the event's own details (title/description/venue/type) — not
     * its dates, same split as the web controller (dates move via
     * reschedule() below).
     */
    public function update(Request $request, Event $event)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_events'), 403);
        abort_unless($event->organization_id === $this->organizationId($request), 404);

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

        ActivityLog::record($request->user()->id, 'event_updated', Event::class, $event->id, $data);

        $event->load('eventDays.sessions', 'fineRules');

        return response()->json(['success' => true, 'data' => $event]);
    }

    /**
     * Delete an event entirely — same cascadeOnDelete + attendance guard
     * as the web controller's destroy().
     */
    public function destroy(Request $request, Event $event)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_events'), 403);
        abort_unless($event->organization_id === $this->organizationId($request), 404);

        if ($event->attendance()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This event already has recorded attendance and can\'t be deleted — the history would be lost.',
            ], 422);
        }

        $title = $event->title;
        ActivityLog::record($request->user()->id, 'event_deleted', Event::class, $event->id, ['title' => $title]);

        $event->delete();

        return response()->json(['success' => true, 'message' => '"' . $title . '" deleted.']);
    }

    /**
     * Change the event's start date — mobile's equivalent of the web
     * calendar's drag-to-reschedule (no drag gesture on table_calendar,
     * so this is a date-picker action on the event detail screen
     * instead). Same EventSetupService::reschedule() call, same
     * attendance guard, so the two surfaces can't drift apart.
     */
    public function reschedule(Request $request, Event $event)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_events'), 403);
        abort_unless($event->organization_id === $this->organizationId($request), 404);

        $data = $request->validate([
            'date_start' => 'required|date',
        ]);

        if ($event->attendance()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This event already has recorded attendance and can no longer be rescheduled. Edit it manually instead.',
            ], 422);
        }

        $deltaDays = EventSetupService::reschedule($event, $data['date_start']);

        if ($deltaDays !== 0) {
            ActivityLog::record($request->user()->id, 'event_rescheduled', Event::class, $event->id, [
                'title'      => $event->title,
                'delta_days' => $deltaDays,
                'date_start' => $event->date_start->toDateString(),
                'date_end'   => $event->date_end->toDateString(),
            ]);
        }

        $event->load('eventDays.sessions', 'fineRules');

        return response()->json(['success' => true, 'data' => $event]);
    }
}
