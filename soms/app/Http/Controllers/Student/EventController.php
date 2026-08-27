<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendance;

class EventController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        $events = Event::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->orderByDesc('date_start')
            ->paginate(12);

        return view('student.events.index', [
            'events' => $events,
        ]);
    }

    public function show(Event $event)
    {
        $user = auth()->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        abort_unless($event->is_published && $orgIds->contains($event->organization_id), 404);

        $event->load(['eventDays.sessions']);

        $myAttendance = EventAttendance::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($a) => $a->event_session_id . '_' . $a->scan_type);

        return view('student.events.show', [
            'event'         => $event,
            'myAttendance'  => $myAttendance,
        ]);
    }
}
