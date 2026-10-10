<?php
// app/Jobs/IssueSessionFinesJob.php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\EventAttendance;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Dual-triggered fine issuance — see 05-Attendance-Fines.md Part A.
 * Trigger 1: 15-min scheduler (routes/console.php) for sessions past
 * timein_end (and timeout_end, if applicable) with fines_issued = false.
 * Trigger 2: officer "Close Session" button (Officer\AttendanceController).
 * Both paths call this job; firstOrCreate keeps it idempotent — running
 * twice for the same session never creates duplicate fines.
 */
class IssueSessionFinesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 100;

    /**
     * @param bool $force Set when triggered by the officer's manual "Close
     *                    Session" button — issues fines immediately for
     *                    every applicable violation type regardless of
     *                    whether the time-out window has closed yet, since
     *                    the officer is explicitly declaring the session
     *                    done. The scheduler-triggered path (force=false)
     *                    only assesses a violation type once its own
     *                    window has actually closed.
     */
    public function __construct(public int $sessionId, public bool $force = false)
    {
    }

    public function handle(): void
    {
        retry(3, function () {
            DB::transaction(function () {
                $session = EventSession::with('eventDay.event')->lockForUpdate()->find($this->sessionId);

                if (!$session) {
                    return;
                }

                $event = $session->eventDay->event;
                $now = now();

                // Draft events haven't been announced to students, so nobody can be fined yet.
                if (!$event->is_published) {
                    return;
                }

                // Every approved student the event applies to (events can target specific programs).
                $students = User::students()->approved()->get()
                    ->filter(fn (User $student) => $event->appliesTo($student));

                $timeInDue = $this->force || $now->greaterThanOrEqualTo($session->timein_end);
                if ($timeInDue) {
                    // 'parade' sessions have no timeout leg (see hasTimeoutRule
                    // below) and the fines/event_fine_rules enum only defines
                    // 'missed_parade' -- not 'missed_parade_timein'. Every other
                    // session_type does use the _timein suffix. Previously this
                    // unconditionally appended '_timein', so a parade absence's
                    // lookup ('missed_parade_timein') never matched any
                    // EventFineRule row and the method returned early without
                    // ever issuing a fine.
                    //
                    // A Morning session flagged is_parade is the parade check-in:
                    // its missed time-in uses the 'missed_parade' rule (higher
                    // amount). Its time-out (if any) still uses the regular rule.
                    $isParadeCheckIn = $session->session_type === 'parade' || $session->is_parade;
                    $timeInSuffix = $isParadeCheckIn
                        ? 'parade'
                        : $session->session_type . '_timein';
                    $this->issueForScanType($session, $event, $students, 'time_in', $timeInSuffix);
                }

                // Time-out is optional per session: no timeout window set = no time-out fine.
                $hasTimeoutRule = $session->session_type !== 'parade' && $session->timeout_end !== null;
                $timeOutDue = $hasTimeoutRule && ($this->force || (
                    $session->timeout_end && $now->greaterThanOrEqualTo($session->timeout_end)
                ));
                if ($timeOutDue) {
                    $this->issueForScanType($session, $event, $students, 'time_out', $session->session_type . '_timeout');
                }

                $fullyAssessed = $this->force || !$hasTimeoutRule || $timeOutDue;
                if ($fullyAssessed) {
                    $session->update(['fines_issued' => true]);
                }
            });
        }, 100);

        ActivityLog::record(null, 'fine_issuance_job_ran', EventSession::class, $this->sessionId);
    }

    protected function issueForScanType($session, $event, $students, string $scanType, string $violationSuffix): void
    {
        $violationType = 'missed_' . $violationSuffix;

        $fineRule = EventFineRule::where('event_id', $event->id)
            ->where('violation_type', $violationType)
            ->first();

        // No rule, or an unset (₱0) amount, means this violation isn't fined.
        if (!$fineRule || (float) $fineRule->amount <= 0) {
            return;
        }

        foreach ($students as $student) {
            $marked = EventAttendance::where([
                'event_session_id' => $session->id,
                'user_id'          => $student->id,
                'scan_type'        => $scanType,
            ])->where('status', 'present')->exists();

            if ($marked) {
                continue;
            }

            $fine = Fine::firstOrCreate(
                [
                    'user_id'           => $student->id,
                    'event_session_id'  => $session->id,
                    'violation_type'    => $violationType,
                ],
                [
                    'event_id'     => $event->id,
                    'event_day_id' => $session->event_day_id,
                    'reason'       => $session->session_type === 'custom'
                        ? 'Missed ' . $session->label . ($scanType === 'time_out' ? ' time-out' : ' time-in')
                        : 'Missed ' . str_replace('_', ' ', $violationSuffix),
                    'amount'       => $fineRule->amount,
                    'issued_at'    => now(),
                    'status'       => 'unpaid',
                ]
            );

            // Only notify on the row actually being created this run — dual
            // triggers (scheduler + "Close Session") both call this method,
            // and firstOrCreate's idempotency means a re-run must not
            // re-notify the student for a fine that already existed.
            if ($fine->wasRecentlyCreated) {
                NotificationService::send($student->id, 'fine_issued', [
                    'fine_id'        => $fine->id,
                    'violation_type' => $violationType,
                    'amount'         => (float) $fine->amount,
                ]);
            }
        }
    }
}