<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\IssueSessionFinesJob;
use App\Models\ActivityLog;
use App\Models\EventAttendance;
use App\Models\EventSession;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mobile officer attendance scanning — live path + offline scan-batch.
 * See 05-Attendance-Fines.md Parts A/B/C and 10-Mobile-Deployment.md Part C.
 */
class AttendanceController extends Controller
{
    /**
     * POST /api/v1/officer/attendance/scan
     * Live path — identical rules to the web scan endpoint, server-receipt
     * time exclusively (Architecture Decision 2.11).
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'session_id' => 'required|exists:event_sessions,id',
            'user_id'    => 'required|exists:users,id',
            'token'      => 'required|string',
        ]);

        $session = EventSession::with('eventDay.event')->findOrFail($data['session_id']);
        $this->authorize('scan', $session);

        $now = now();
        $result = $this->processScan($session, $data['user_id'], $data['token'], $now, $now, false);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * POST /api/v1/officer/attendance/scan-batch
     * Offline sync path — see 05-Attendance-Fines.md Part C and
     * 03-Auth-Security.md §20.10 for the bounded clock-drift check.
     */
    public function scanBatch(Request $request)
    {
        $request->validate([
            'scans'                      => 'required|array|max:200',
            'scans.*.token'              => 'required|string',
            'scans.*.user_id'            => 'required|integer|exists:users,id',
            'scans.*.session_id'         => 'required|integer|exists:event_sessions,id',
            'scans.*.device_scanned_at'  => 'required|date',
        ]);

        $results = [];
        $serverReceivedAt = now();

        foreach ($request->input('scans') as $item) {
            $results[] = $this->processSingleOfflineScan($item, $serverReceivedAt);
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    protected function processSingleOfflineScan(array $item, Carbon $serverReceivedAt): array
    {
        $session = EventSession::with('eventDay.event')->find($item['session_id']);
        if (!$session) {
            return ['session_id' => $item['session_id'], 'user_id' => $item['user_id'], 'status' => 'rejected', 'reason' => 'Session not found'];
        }

        $this->authorize('scan', $session);

        $deviceScannedAt = Carbon::parse($item['device_scanned_at']);
        $drift = abs($deviceScannedAt->diffInMinutes($serverReceivedAt));

        if ($drift > 10) {
            ActivityLog::record(auth()->id(), 'offline_scan_clock_drift_flagged', EventSession::class, $session->id, [
                'device_scanned_at'  => $deviceScannedAt->toIso8601String(),
                'server_received_at' => $serverReceivedAt->toIso8601String(),
                'drift_minutes'      => $drift,
            ]);

            return $this->processScan($session, $item['user_id'], $item['token'], $deviceScannedAt, $serverReceivedAt, true, forceFlag: true);
        }

        return $this->processScan($session, $item['user_id'], $item['token'], $deviceScannedAt, $serverReceivedAt, true);
    }

    /**
     * Identical validation chain for both the live single-scan endpoint and
     * each item in an offline batch: token verification, time-window check,
     * duplicate check — not a separate, lighter-weight check.
     */
    protected function processScan(
        EventSession $session,
        int $userId,
        string $token,
        Carbon $windowReferenceTime,
        Carbon $serverReceivedAt,
        bool $isOffline,
        bool $forceFlag = false
    ): array {
        return DB::transaction(function () use ($session, $userId, $token, $windowReferenceTime, $serverReceivedAt, $isOffline, $forceFlag) {
            $user = User::where('id', $userId)->where('is_approved', true)->first();
            if (!$user) {
                return ['session_id' => $session->id, 'user_id' => $userId, 'status' => 'rejected', 'reason' => 'Student not approved or not found'];
            }

            if (!QrTokenService::verify($user, $token, $windowReferenceTime->timestamp)) {
                return ['session_id' => $session->id, 'user_id' => $userId, 'status' => 'rejected', 'reason' => 'Invalid or expired token'];
            }

            $withinWindow = $windowReferenceTime->betweenIncluded(
                Carbon::parse($session->timein_start),
                Carbon::parse($session->timein_end)
            );

            $exists = EventAttendance::where([
                'event_session_id' => $session->id,
                'user_id'          => $user->id,
                'scan_type'        => 'time_in',
            ])->first();

            if ($exists) {
                return ['session_id' => $session->id, 'user_id' => $userId, 'status' => 'already_marked', 'student_name' => $user->name];
            }

            $status = $forceFlag ? 'flagged_for_review' : ($withinWindow ? 'present' : 'absent');

            if (!$withinWindow && !$forceFlag) {
                return ['session_id' => $session->id, 'user_id' => $userId, 'status' => 'rejected', 'reason' => 'Outside scan window'];
            }

            $attendance = EventAttendance::create([
                'event_id'                   => $session->eventDay->event_id,
                'event_day_id'               => $session->event_day_id,
                'event_session_id'           => $session->id,
                'user_id'                    => $user->id,
                'scan_type'                  => 'time_in',
                'scanned_at'                 => $serverReceivedAt,
                'marked_by'                  => auth()->id(),
                'status'                     => $status,
                'device_scanned_at'          => $isOffline ? $windowReferenceTime : null,
                'synced_from_offline_queue'  => $isOffline,
            ]);

            return [
                'session_id'   => $session->id,
                'user_id'      => $userId,
                'status'       => $status,
                'student_name' => $user->name,
                'attendance_id'=> $attendance->id,
            ];
        });
    }

    /**
     * POST /api/v1/officer/attendance/sessions/{session}/close
     */
    public function closeSession(EventSession $session)
    {
        $this->authorize('close', $session);

        IssueSessionFinesJob::dispatchSync($session->id, true);
        ActivityLog::record(auth()->id(), 'session_closed_manually', EventSession::class, $session->id);

        return response()->json(['success' => true, 'message' => 'Session closed — fines issued.']);
    }
}
