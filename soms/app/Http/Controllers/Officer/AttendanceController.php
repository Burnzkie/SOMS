<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\IssueSessionFinesJob;
use App\Models\ActivityLog;
use App\Models\EventAttendance;
use App\Models\EventSession;
use App\Models\Organization;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Dual-session live attendance scanning — see 05-Attendance-Fines.md Part A/B.
 * Web scan-station flow only; the offline Flutter path lives in
 * Api\Officer\AttendanceController::scanBatch.
 */
class AttendanceController extends Controller
{
    /**
     * Scan station page for a given session. Loaded once, then held open
     * against the HID scanner input for the duration of the session
     * (ScanStationIdleTimeout middleware covers the 5-min idle logout).
     */
    public function station(EventSession $session)
    {
        $this->authorize('scan', $session);

        $session->load('eventDay.event');

        return view('officer.attendance.scan', compact('session'));
    }

    /**
     * POST /officer/attendance/scan
     * Core logic mirrors 05-Attendance-Fines.md Part B AttendanceController@scan.
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'session_id' => 'required|exists:event_sessions,id',
            'token'      => 'required|string',
        ]);

        $session = EventSession::with('eventDay.event')->findOrFail($data['session_id']);
        $this->authorize('scan', $session);

        $now = now();
        $withinWindow = $now->betweenIncluded(
            Carbon::parse($session->timein_start),
            Carbon::parse($session->timein_end)
        );

        if (!$withinWindow) {
            return response()->json(['status' => 'rejected', 'reason' => 'Outside scan window']);
        }

        // Token payload for a live scan is just the HMAC token; the scanning
        // officer's device doesn't know which student it belongs to ahead of
        // time, so we brute-force match against currently-approved users is
        // too expensive at scale — instead the client sends user_id alongside
        // the token captured from the QR (see 10-Mobile-Deployment.md /
        // resources/views/officer/attendance/scan.blade.php for the payload
        // format: "<user_id>:<token>").
        [$userId, $token] = array_pad(explode(':', $data['token'], 2), 2, null);

        $user = User::where('id', $userId)->where('is_approved', true)->first();
        abort_if(!$user || !$token, 422, 'Invalid QR payload.');
        abort_unless(QrTokenService::verify($user, $token, $now->timestamp), 422, 'Invalid or expired token.');

        $exists = EventAttendance::where([
            'event_session_id' => $session->id,
            'user_id'          => $user->id,
            'scan_type'        => 'time_in',
        ])->exists();

        if ($exists) {
            return response()->json(['status' => 'already_marked', 'student_name' => $user->name]);
        }

        EventAttendance::create([
            'event_id'         => $session->eventDay->event_id,
            'event_day_id'     => $session->event_day_id,
            'event_session_id' => $session->id,
            'user_id'          => $user->id,
            'scan_type'        => 'time_in',
            'scanned_at'       => $now,
            'marked_by'        => auth()->id(),
            'status'           => 'present',
        ]);

        return response()->json([
            'status'       => 'present',
            'student_name' => $user->name,
            'department'   => $user->department,
            'session'      => $session->session_type,
        ]);
    }

    /**
     * "Close Session" — manual fallback trigger for fine issuance, in
     * addition to the 15-min scheduler. See Architecture Decision 2.13.
     */
    public function closeSession(EventSession $session)
    {
        $this->authorize('close', $session);

        IssueSessionFinesJob::dispatchSync($session->id, true);

        ActivityLog::record(auth()->id(), 'session_closed_manually', EventSession::class, $session->id);

        return back()->with('status', 'Session closed — fines issued for students who missed time-in/time-out.');
    }

    /**
     * Manual attendance override — requires the officer's own live
     * password re-auth (03-Auth-Security.md §20.2). Never queueable offline.
     */
    public function manualOverride(Request $request, EventSession $session)
    {
        $this->authorize('override', $session);

        $data = $request->validate([
            'password'        => 'required',
            'student_id'      => 'required|exists:users,student_id',
            'override_reason' => 'required|string|min:5',
            'scan_type'       => 'required|in:time_in,time_out',
        ]);

        if (!Hash::check($data['password'], auth()->user()->password)) {
            ActivityLog::record(auth()->id(), 'override_reauth_failed', EventSession::class, $session->id);
            abort(403, 'Re-authentication failed.');
        }

        $student = User::where('student_id', $data['student_id'])->firstOrFail();

        $attendance = EventAttendance::updateOrCreate(
            [
                'event_session_id' => $session->id,
                'user_id'          => $student->id,
                'scan_type'        => $data['scan_type'],
            ],
            [
                'event_id'            => $session->eventDay->event_id,
                'event_day_id'        => $session->event_day_id,
                'scanned_at'          => now(),
                'marked_by'           => auth()->id(),
                'status'              => 'present',
                'is_manual_override'  => true,
                'override_reason'     => $data['override_reason'],
            ]
        );

        ActivityLog::record(auth()->id(), 'attendance_manual_override', EventAttendance::class, $attendance->id, [
            'student_id' => $student->student_id,
            'reason'     => $data['override_reason'],
        ]);

        return back()->with('status', "Manual override recorded for {$student->name}.");
    }
}
