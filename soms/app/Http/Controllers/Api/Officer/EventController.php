<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Read-only event/session listing for the Flutter officer app's scan
 * screen (session picker) and dashboard event cards. Event creation
 * remains a web-only flow for now (Officer\EventController).
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
}
