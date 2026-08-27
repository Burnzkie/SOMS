<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Officer\CalendarController as WebCalendarController;
use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * GET /api/v1/officer/calendar — consumed by the Flutter table_calendar
 * widget. See 08-Announcements-Calendar-Notifications.md ("Both pull from
 * GET /api/v1/officer/calendar"). Reuses the same feed-building logic as
 * the web FullCalendar.js source (Officer\CalendarController::buildEventFeed)
 * so the two surfaces never drift apart.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');

        $feed = WebCalendarController::buildEventFeed($orgId);

        return response()->json(['success' => true, 'data' => $feed]);
    }
}
