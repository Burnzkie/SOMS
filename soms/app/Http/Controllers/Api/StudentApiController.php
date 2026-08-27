<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StudentApiController extends Controller
{
    /**
     * GET /api/v1/student/dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        $upcomingEvents = Event::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->where('date_end', '>=', now()->toDateString())
            ->orderBy('date_start')
            ->take(5)
            ->get(['id', 'title', 'venue', 'date_start', 'date_end']);

        $recentAnnouncements = Announcement::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'unpaid_fines_count'  => $user->fines()->where('status', 'unpaid')->count(),
                'unpaid_fines_amount' => $user->fines()->where('status', 'unpaid')->sum('amount'),
                'upcoming_events'     => $upcomingEvents,
                'recent_announcements' => $recentAnnouncements,
            ],
        ]);
    }

    /**
     * GET /api/v1/student/qr
     *
     * Returns the live rotating QR token (~60s validity, see
     * App\Services\QrTokenService and 05-Attendance-Fines.md Part B). There
     * is no stored token anymore, so the Flutter app must poll this
     * endpoint periodically (well within WINDOW_SECONDS) rather than
     * generate/cache the QR offline -- see 10-Mobile-Deployment.md for the
     * connectivity tradeoff this introduces versus the old static token.
     */
    public function qrCurrent(Request $request)
    {
        $user = $request->user();

        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not available yet. Please wait for Admin approval.',
            ], 409);
        }

        $current = QrTokenService::current($user);

        return response()->json([
            'success' => true,
            'data' => [
                // Flutter renders the QR client-side via qr_flutter from
                // "qr_payload" ("<user_id>:<token>") -- the scan station needs
                // user_id alongside the token since the HMAC alone doesn't
                // identify which student it belongs to. "token" is kept
                // separately for any UI that just wants to display/debug it.
                'user_id'     => $user->id,
                'token'       => $current['token'],
                'qr_payload'  => $user->id . ':' . $current['token'],
                'expires_in'  => $current['expires_in'],
                'server_time' => $current['server_time'],
            ],
        ]);
    }

    /**
     * GET /api/v1/student/events
     */
    public function events(Request $request)
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        $events = Event::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->orderByDesc('date_start')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $events]);
    }

    /**
     * GET /api/v1/student/events/{event}
     */
    public function eventShow(Request $request, Event $event)
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        abort_unless($event->is_published && $orgIds->contains($event->organization_id), 404);

        $event->load(['eventDays.sessions']);

        $myAttendance = EventAttendance::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->get(['event_session_id', 'scan_type', 'status', 'scanned_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'event'         => $event,
                'my_attendance' => $myAttendance,
            ],
        ]);
    }

    /**
     * GET /api/v1/student/fines
     */
    public function fines(Request $request)
    {
        $user = $request->user();

        $query = $user->fines()->with(['event:id,title', 'eventSession:id,session_type']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $fines = $query->orderByDesc('issued_at')->paginate(15);

        return response()->json(['success' => true, 'data' => $fines]);
    }

    /**
     * GET /api/v1/student/announcements
     */
    public function announcements(Request $request)
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');
        $orgId = $orgIds->first();

        $announcements = $orgId
            ? Cache::remember("public:announcements:org:{$orgId}", 30, function () use ($orgId) {
                return Announcement::where('organization_id', $orgId)
                    ->where('is_published', true)
                    ->latest()
                    ->get();
            })
            : collect();

        return response()->json(['success' => true, 'data' => $announcements]);
    }

    /**
     * GET /api/v1/student/announcements/{announcement}
     */
    public function announcementShow(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        abort_unless($announcement->is_published && $orgIds->contains($announcement->organization_id), 404);

        return response()->json(['success' => true, 'data' => $announcement]);
    }
}
