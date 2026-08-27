<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CalendarEntry;
use App\Models\Organization;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Officer Calendar — see 08-Announcements-Calendar-Notifications.md.
 *
 * v4.0: only two entry types remain — SOMS Events (blue, from `events`,
 * spanning date_start..date_end) and Custom Entries (grey, from
 * calendar_entries). "Election Key Dates" and "Game Matchups" were
 * removed with their source tables.
 *
 * The page itself (index) is Blade + FullCalendar.js per spec, fed by
 * server-rendered JSON rather than a client-side fetch to the API route
 * — this avoids relying on the browser's web session cookie also being
 * accepted by the auth:sanctum-gated /api/v1 group, which isn't
 * guaranteed to "just work" without additional Sanctum SPA config. The
 * *data query* is identical either way; Api\Officer\CalendarController
 * exposes the same shape at GET /api/v1/officer/calendar for the
 * Flutter app's table_calendar widget.
 */
class CalendarController extends Controller
{
    protected function organizationId(): ?int
    {
        return auth()->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index()
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'view_calendar') || OfficerPermission::can(auth()->user(), 'manage_calendar'), 403);

        $orgId = $this->organizationId();
        $canManage = OfficerPermission::can(auth()->user(), 'manage_calendar');

        $events = $this->buildEventFeed($orgId);
        $entries = CalendarEntry::where('organization_id', $orgId)->orderBy('date')->get();

        return view('officer.calendar.index', [
            'eventsJson' => $events->toJson(),
            'entries'    => $entries,
            'canManage'  => $canManage,
        ]);
    }

    /**
     * Shared data shape (also used by Api\Officer\CalendarController) —
     * blue SOMS Events (date_start..date_end spans) + grey Custom Entries.
     */
    public static function buildEventFeed(?int $orgId)
    {
        $events = \App\Models\Event::where('organization_id', $orgId)
            ->get()
            ->map(fn ($e) => [
                'id'    => 'event-' . $e->id,
                'title' => $e->title,
                'start' => $e->date_start->toDateString(),
                'end'   => $e->date_end->copy()->addDay()->toDateString(), // FullCalendar end is exclusive
                'color' => '#5B5BF6', // blue — SOMS Events
                'type'  => 'event',
            ]);

        $entries = CalendarEntry::where('organization_id', $orgId)
            ->get()
            ->map(fn ($ce) => [
                'id'    => 'entry-' . $ce->id,
                'title' => $ce->title,
                'start' => $ce->date->toDateString(),
                'color' => '#8C90A3', // grey — Custom Entries
                'type'  => 'entry',
            ]);

        return $events->concat($entries)->values();
    }

    /**
     * Add a custom entry — Executive/Administrative only.
     */
    public function store(Request $request)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_calendar'), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'date'  => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $entry = CalendarEntry::create([
            'organization_id' => $this->organizationId(),
            'created_by'      => auth()->id(),
            'title'           => $data['title'],
            'date'            => $data['date'],
            'notes'           => $data['notes'] ?? null,
        ]);

        ActivityLog::record(auth()->id(), 'calendar_entry_created', CalendarEntry::class, $entry->id, ['title' => $entry->title]);

        return back()->with('status', 'Calendar entry added.');
    }

    /**
     * Edit a custom entry — Executive/Administrative only.
     */
    public function update(Request $request, CalendarEntry $entry)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_calendar'), 403);
        abort_unless($entry->organization_id === $this->organizationId(), 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'date'  => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $entry->update($data);

        ActivityLog::record(auth()->id(), 'calendar_entry_updated', CalendarEntry::class, $entry->id, $data);

        return back()->with('status', 'Calendar entry updated.');
    }

    /**
     * Delete a custom entry — Executive/Administrative only.
     */
    public function destroy(CalendarEntry $entry)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_calendar'), 403);
        abort_unless($entry->organization_id === $this->organizationId(), 404);

        ActivityLog::record(auth()->id(), 'calendar_entry_deleted', CalendarEntry::class, $entry->id, ['title' => $entry->title]);

        $entry->delete();

        return back()->with('status', 'Calendar entry removed.');
    }
}
