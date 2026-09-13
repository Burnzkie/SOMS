<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\EventTypes;
use App\Support\OfficerPermission;

/**
 * Officer Calendar — see 08-Announcements-Calendar-Notifications.md.
 *
 * v4.1: Custom Entries (calendar_entries, the grey non-attendance markers)
 * were fully removed — SOMS Events (from `events`) are now the only thing
 * on this calendar. "Election Key Dates" and "Game Matchups" were already
 * removed with their source tables back in v4.0.
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
        $canManageEvents = OfficerPermission::can(auth()->user(), 'manage_events');

        $events = $this->buildEventFeed($orgId);

        // Security audit (Sep 2026): these are embedded straight into
        // <script> blocks in the Blade view via {!! !!} (needed so
        // FullCalendar/the quick-create modal get real JS objects, not
        // escaped HTML entities). The HEX flags make that safe — event
        // titles/venues are officer-entered free text, and without these
        // flags a title containing `</script><script>` could break out of
        // the tag. json_decode() on the Blade side (used for the
        // server-rendered @foreach lists further up this same view) is
        // unaffected — it transparently understands the \uXXXX escapes.
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return view('officer.calendar.index', [
            'eventsJson' => $events->toJson($jsonFlags),
            'canManageEvents' => $canManageEvents,
            'eventTypesJson' => json_encode(EventTypes::TYPES, $jsonFlags),
            'foundationDayActivitiesJson' => json_encode(EventTypes::FOUNDATION_DAY_ACTIVITIES, $jsonFlags),
        ]);
    }

    /**
     * Shared data shape (also used by Api\Officer\CalendarController) —
     * blue SOMS Events (date_start..date_end spans).
     */
    public static function buildEventFeed(?int $orgId)
    {
        return \App\Models\Event::where('organization_id', $orgId)
            ->get()
            ->map(fn ($e) => [
                'id'    => 'event-' . $e->id,
                'title' => $e->title,
                'start' => $e->date_start->toDateString(),
                'end'   => $e->date_end->copy()->addDay()->toDateString(), // FullCalendar end is exclusive
                'color' => $e->color ?? '#FF7A29', // officer-picked, falls back to the brand orange
                'type'  => 'event',
            ])
            ->values();
    }
}
