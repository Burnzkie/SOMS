<?php
// app/Services/FlaggedScanReviewService.php

namespace App\Services;

use App\Jobs\IssueSessionFinesJob;
use App\Models\ActivityLog;
use App\Models\EventAttendance;

/**
 * Decides offline scans that were flagged for device clock drift.
 * Shared by the web and mobile (API) officer controllers so both apply
 * exactly the same rules.
 *
 * Approve -> 'present'.
 * Reject  -> 'absent', then fine issuance is re-dispatched: the session may
 * already be fines_issued = true, so the scheduler would never revisit it.
 * Re-dispatch is safe (firstOrCreate keeps it idempotent) and force=false
 * means time-out fines are not issued early.
 */
class FlaggedScanReviewService
{
    /**
     * @return bool false when the scan was not waiting for review (already
     *              decided, or never flagged) -- nothing is changed then.
     */
    public function review(EventAttendance $attendance, bool $approve, int $officerId): bool
    {
        // Conditional UPDATE: if two officers decide at once, only one wins.
        $updated = EventAttendance::where('id', $attendance->id)
            ->where('status', 'flagged_for_review')
            ->update([
                'status'          => $approve ? 'present' : 'absent',
                // Keep the resolution history: status alone forgets it was ever flagged.
                'reviewed_by'     => $officerId,
                'reviewed_at'     => now(),
                'review_decision' => $approve ? 'approved' : 'rejected',
            ]);

        if ($updated === 0) {
            return false;
        }

        $attendance->refresh();

        ActivityLog::record(
            $officerId,
            $approve ? 'flagged_scan_approved' : 'flagged_scan_rejected',
            EventAttendance::class,
            $attendance->id,
            ['student_id' => $attendance->user_id, 'session_id' => $attendance->event_session_id]
        );

        if (!$approve) {
            IssueSessionFinesJob::dispatch($attendance->event_session_id);
        }

        return true;
    }
}
