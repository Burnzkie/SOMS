<?php
// app/Services/AuditService.php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AuditFinding;
use App\Models\EventAttendance;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only audit checks over fines, payments and attendance. Nothing here
 * writes or "fixes" a record: problems are reported as discrepancies, and
 * the Auditor raises findings for them.
 *
 * Each check is its own small method and returns issues in one shape:
 *   check, severity, reference_type, reference_id, student, event, summary
 */
class AuditService
{
    /** Statuses that excuse a fine -- must match IssueSessionFinesJob. */
    private const ATTENDED_STATUSES = ['present', 'flagged_for_review'];

    /** Cap per check so a badly broken dataset can't exhaust memory. */
    private const LIMIT_PER_CHECK = 200;

    public const SEVERITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    public const CHECK_LABELS = [
        'duplicate_receipt'         => 'Receipt used for different students',
        'settled_without_officer'   => 'Settled with no officer recorded',
        'fine_despite_attendance'   => 'Fine issued although student attended',
        'missing_fine'              => 'Expected fine is missing',
        'amount_differs_from_rule'  => 'Fine amount differs from the fine rule',
        'weak_waiver_reason'        => 'Waiver has a weak reason',
        'flagged_scan_stale'        => 'Flagged scan not reviewed',
        'paid_without_receipt'      => 'Paid with no receipt number',
    ];

    /** Totals for the dashboard. Collections = fines marked paid by an officer. */
    public function summary(): array
    {
        $byStatus = Fine::query()
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $status) => (int) ($byStatus[$status]->n ?? 0);
        $total = fn (string $status) => (float) ($byStatus[$status]->total ?? 0);

        return [
            'assessed_count'    => $byStatus->sum('n'),
            'assessed_total'    => (float) $byStatus->sum('total'),
            'collected_count'   => $count('paid'),
            'collected_total'   => $total('paid'),
            'waived_count'      => $count('waived'),
            'waived_total'      => $total('waived'),
            'outstanding_count' => $count('unpaid'),
            'outstanding_total' => $total('unpaid'),
            'pending_flagged'   => EventAttendance::where('status', 'flagged_for_review')->count(),
            'active_findings'   => AuditFinding::active()->count(),
        ];
    }

    /**
     * Every discrepancy found right now, most severe first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function discrepancies(): Collection
    {
        return collect()
            ->merge($this->duplicateReceipts())
            ->merge($this->settledWithoutOfficer())
            ->merge($this->fineDespiteAttendance())
            ->merge($this->missingFines())
            ->merge($this->amountDiffersFromRule())
            ->merge($this->weakWaiverReasons())
            ->merge($this->staleFlaggedScans())
            ->merge($this->paidWithoutReceipt())
            ->sortBy(fn (array $issue) => [self::SEVERITY_ORDER[$issue['severity']], $issue['check'], $issue['reference_id']])
            ->values();
    }

    // ------------------------------------------------------------------
    // Read-only record views (shared by the web pages and the mobile API)
    // ------------------------------------------------------------------

    /**
     * Sessions, newest first, with their attendance totals and fine counts.
     *
     * @return array{sessions: LengthAwarePaginator, counts: Collection, fineCounts: Collection}
     */
    public function attendanceBySession(int $perPage = 15): array
    {
        $sessions = EventSession::with('eventDay.event:id,title')
            ->whereHas('eventDay.event')
            ->orderByDesc('timein_start')
            ->paginate($perPage);

        $ids = $sessions->pluck('id');

        $counts = EventAttendance::query()
            ->selectRaw('event_session_id')
            ->selectRaw("SUM(CASE WHEN status = 'present' AND review_decision IS NULL THEN 1 ELSE 0 END) AS present_count")
            ->selectRaw("SUM(CASE WHEN status = 'flagged_for_review' THEN 1 ELSE 0 END) AS pending_count")
            ->selectRaw("SUM(CASE WHEN review_decision = 'approved' THEN 1 ELSE 0 END) AS approved_count")
            ->selectRaw("SUM(CASE WHEN review_decision = 'rejected' THEN 1 ELSE 0 END) AS rejected_count")
            ->selectRaw('SUM(CASE WHEN is_manual_override = 1 THEN 1 ELSE 0 END) AS override_count')
            ->whereIn('event_session_id', $ids)
            ->groupBy('event_session_id')
            ->get()
            ->keyBy('event_session_id');

        $fineCounts = Fine::selectRaw('event_session_id, COUNT(*) AS n')
            ->whereIn('event_session_id', $ids)
            ->groupBy('event_session_id')
            ->pluck('n', 'event_session_id');

        return compact('sessions', 'counts', 'fineCounts');
    }

    /** Every scan that was flagged for clock drift: still waiting, or already decided. */
    public function flaggedScanHistory(int $limit = 25): Collection
    {
        return EventAttendance::with(['user:id,name,student_id', 'eventSession.eventDay.event:id,title', 'reviewer:id,name'])
            ->where(fn ($q) => $q->whereNotNull('review_decision')->orWhere('status', 'flagged_for_review'))
            ->latest('scanned_at')
            ->limit($limit)
            ->get();
    }

    /** Attendance an officer marked by hand (an override replaces the scan that was there). */
    public function manualOverrides(int $limit = 25): Collection
    {
        return EventAttendance::with(['user:id,name,student_id', 'eventSession.eventDay.event:id,title', 'markedBy:id,name'])
            ->where('is_manual_override', true)
            ->latest('scanned_at')
            ->limit($limit)
            ->get();
    }

    /** Activity log query with the optional action / date filters. */
    public function activityLogs(?string $action, ?string $from, ?string $to): Builder
    {
        return ActivityLog::query()
            ->when($action, fn ($q, $a) => $q->where('action', $a))
            ->when($from, fn ($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($to, fn ($q, $t) => $q->whereDate('created_at', '<=', $t));
    }

    /** Distinct action names, for the log's filter. */
    public function logActions(): Collection
    {
        return ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
    }

    /** Whether the activity log's hash chain verifies. Cached: the check reads every row. */
    public function logChainOk(): bool
    {
        return Cache::remember('audit.log-chain-ok', 300, fn () => ActivityLog::verifyChainIntegrity());
    }

    // ------------------------------------------------------------------
    // Payment checks
    // ------------------------------------------------------------------

    /** The same receipt number recorded against fines of different students. */
    private function duplicateReceipts(): Collection
    {
        $receipts = Fine::query()
            ->whereNotNull('receipt_number')
            ->where('receipt_number', '!=', '')
            ->groupBy('receipt_number')
            ->havingRaw('COUNT(DISTINCT user_id) > 1')
            ->limit(self::LIMIT_PER_CHECK)
            ->pluck('receipt_number');

        if ($receipts->isEmpty()) {
            return collect();
        }

        return Fine::with(['user:id,name,student_id', 'event:id,title'])
            ->whereIn('receipt_number', $receipts)
            ->orderBy('id')
            ->get()
            ->groupBy('receipt_number')
            ->map(function (Collection $fines, string $receipt) {
                $first = $fines->first();
                $students = $fines->map(fn (Fine $f) => $f->user->name)->unique()->implode(', ');

                return $this->issue(
                    'duplicate_receipt', 'high', 'fine', $first->id, $first,
                    "Receipt {$receipt} is recorded for {$fines->pluck('user_id')->unique()->count()} different students ({$students})."
                );
            })
            ->values();
    }

    /** Paid or waived, but no officer is recorded as having settled it. */
    private function settledWithoutOfficer(): Collection
    {
        return $this->fineIssues(
            Fine::whereIn('status', ['paid', 'waived'])->whereNull('cleared_by'),
            'settled_without_officer', 'high',
            fn (Fine $f) => "Fine #{$f->id} is {$f->status} but no officer is recorded as having settled it."
        );
    }

    /** A waiver whose reason is too short to count as supporting evidence. */
    private function weakWaiverReasons(): Collection
    {
        $min = config('soms.min_waiver_reason_length');

        return Fine::with(['user:id,name,student_id', 'event:id,title'])
            ->where('status', 'waived')
            ->get()
            ->filter(fn (Fine $f) => mb_strlen(trim((string) $f->reason)) < $min)
            ->take(self::LIMIT_PER_CHECK)
            ->map(fn (Fine $f) => $this->issue(
                'weak_waiver_reason', 'medium', 'fine', $f->id, $f,
                "Fine #{$f->id} was waived with the reason \"{$f->reason}\" (under {$min} characters)."
            ))
            ->values();
    }

    /** Marked paid with no receipt number recorded. */
    private function paidWithoutReceipt(): Collection
    {
        return $this->fineIssues(
            Fine::paid()->where(fn ($q) => $q->whereNull('receipt_number')->orWhere('receipt_number', '')),
            'paid_without_receipt', 'low',
            fn (Fine $f) => "Fine #{$f->id} (₱" . number_format((float) $f->amount, 2) . ') is marked paid with no receipt number.'
        );
    }

    // ------------------------------------------------------------------
    // Fine-vs-rule and fine-vs-attendance checks
    // ------------------------------------------------------------------

    /**
     * The fine's amount no longer equals the event's fine rule. Can be a
     * wrong amount, or a rule edited after the fine was issued: the amount
     * is copied onto the fine at issue time, so only a person can tell which.
     */
    private function amountDiffersFromRule(): Collection
    {
        $fines = Fine::query()
            ->join('event_fine_rules as rules', function ($join) {
                $join->on('rules.event_id', '=', 'fines.event_id')
                    ->on('rules.violation_type', '=', 'fines.violation_type');
            })
            ->whereColumn('fines.amount', '!=', 'rules.amount')
            ->select('fines.*', 'rules.amount as rule_amount')
            ->with(['user:id,name,student_id', 'event:id,title'])
            ->limit(self::LIMIT_PER_CHECK)
            ->get();

        return $fines->map(fn (Fine $f) => $this->issue(
            'amount_differs_from_rule', 'medium', 'fine', $f->id, $f,
            'Fine #' . $f->id . ' is ₱' . number_format((float) $f->amount, 2)
                . ' but the current rule for ' . $f->violation_type . ' is ₱' . number_format((float) $f->rule_amount, 2) . '.'
        ));
    }

    /** A fine exists for a scan the student actually made (or is awaiting review). */
    private function fineDespiteAttendance(): Collection
    {
        $fines = Fine::with(['user:id,name,student_id', 'event:id,title'])
            ->whereNotNull('event_session_id')
            ->get();

        if ($fines->isEmpty()) {
            return collect();
        }

        $attended = $this->attendedKeys($fines->pluck('event_session_id')->unique());

        return $fines
            ->filter(fn (Fine $f) => isset($attended[$this->scanKey($f->event_session_id, $f->user_id, $this->scanTypeOf($f->violation_type))]))
            ->take(self::LIMIT_PER_CHECK)
            ->map(fn (Fine $f) => $this->issue(
                'fine_despite_attendance', 'high', 'fine', $f->id, $f,
                "Fine #{$f->id} ({$f->violation_type}, {$f->status}) exists but the student has an attendance record for that scan."
            ))
            ->values();
    }

    /**
     * A student who should have been fined for a closed session and wasn't:
     * the same rules IssueSessionFinesJob applies, recomputed from the data.
     * Set-based on purpose -- a handful of queries however many sessions exist.
     */
    private function missingFines(): Collection
    {
        $sessions = EventSession::with('eventDay.event')->where('fines_issued', true)->get()
            ->filter(fn (EventSession $s) => $s->eventDay?->event?->is_published);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $sessionIds = $sessions->pluck('id');
        $eventIds = $sessions->map(fn (EventSession $s) => $s->eventDay->event_id)->unique();

        $rules = EventFineRule::whereIn('event_id', $eventIds)->where('amount', '>', 0)->get()
            ->mapWithKeys(fn ($r) => [$r->event_id . '|' . $r->violation_type => true]);

        $attended = $this->attendedKeys($sessionIds);
        $fined = Fine::whereIn('event_session_id', $sessionIds)->get(['user_id', 'event_session_id', 'violation_type'])
            ->mapWithKeys(fn ($f) => [$f->event_session_id . '|' . $f->user_id . '|' . $f->violation_type => true]);

        $students = User::students()->approved()->get(['id', 'name', 'student_id', 'program', 'created_at']);

        $issues = collect();

        foreach ($sessions as $session) {
            $event = $session->eventDay->event;

            foreach ($this->scansExpected($session) as $scanType => $violationType) {
                if (!isset($rules[$event->id . '|' . $violationType])) {
                    continue; // no rule, or a ₱0 rule: nothing is fined for this
                }

                foreach ($students as $student) {
                    // Registered after the session closed: not expected to be fined.
                    if ($student->created_at->greaterThan($session->timein_end) || !$event->appliesTo($student)) {
                        continue;
                    }
                    if (isset($attended[$this->scanKey($session->id, $student->id, $scanType)])
                        || isset($fined[$session->id . '|' . $student->id . '|' . $violationType])) {
                        continue;
                    }

                    $issues->push([
                        'check'          => 'missing_fine',
                        'severity'       => 'high',
                        'reference_type' => 'session',
                        'reference_id'   => $session->id,
                        'student'        => "{$student->name} ({$student->student_id})",
                        'event'          => $event->title,
                        'summary'        => "{$student->name} has no attendance and no {$violationType} fine for {$event->title} ({$session->label}).",
                    ]);

                    if ($issues->count() >= self::LIMIT_PER_CHECK) {
                        return $issues;
                    }
                }
            }
        }

        return $issues;
    }

    // ------------------------------------------------------------------
    // Attendance checks
    // ------------------------------------------------------------------

    /** A flagged offline scan nobody has decided on for too long. */
    private function staleFlaggedScans(): Collection
    {
        $days = config('soms.stale_flagged_scan_days');

        return EventAttendance::with(['user:id,name,student_id', 'event:id,title'])
            ->where('status', 'flagged_for_review')
            ->where('scanned_at', '<', now()->subDays($days))
            ->limit(self::LIMIT_PER_CHECK)
            ->get()
            ->map(fn (EventAttendance $a) => [
                'check'          => 'flagged_scan_stale',
                'severity'       => 'medium',
                'reference_type' => 'attendance',
                'reference_id'   => $a->id,
                'student'        => "{$a->user->name} ({$a->user->student_id})",
                'event'          => $a->event?->title,
                'summary'        => "Scan #{$a->id} has been waiting for review since {$a->scanned_at->format('M j, Y')} (over {$days} days).",
            ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * The scans a closed session is expected to fine for, mirroring
     * IssueSessionFinesJob: time-in always; time-out only when a time-out
     * window was set and the session isn't a legacy parade session.
     *
     * @return array<string, string> scan type => violation type
     */
    private function scansExpected(EventSession $session): array
    {
        $isParadeCheckIn = $session->session_type === 'parade' || $session->is_parade;

        $expected = [
            'time_in' => $isParadeCheckIn ? 'missed_parade' : "missed_{$session->session_type}_timein",
        ];

        if ($session->session_type !== 'parade' && $session->timeout_end !== null) {
            $expected['time_out'] = "missed_{$session->session_type}_timeout";
        }

        return $expected;
    }

    private function scanTypeOf(string $violationType): string
    {
        return str_ends_with($violationType, '_timeout') ? 'time_out' : 'time_in';
    }

    private function scanKey(int $sessionId, int $userId, string $scanType): string
    {
        return "{$sessionId}|{$userId}|{$scanType}";
    }

    /** Lookup set of "session|user|scan" for every scan that excuses a fine. */
    private function attendedKeys(Collection $sessionIds): array
    {
        $keys = [];

        EventAttendance::whereIn('event_session_id', $sessionIds)
            ->whereIn('status', self::ATTENDED_STATUSES)
            ->get(['user_id', 'event_session_id', 'scan_type'])
            ->each(function ($a) use (&$keys) {
                $keys[$this->scanKey($a->event_session_id, $a->user_id, $a->scan_type)] = true;
            });

        return $keys;
    }

    /** Builds fine-based issues from a query, newest first, capped. */
    private function fineIssues($query, string $check, string $severity, callable $summary): Collection
    {
        return $query->with(['user:id,name,student_id', 'event:id,title'])
            ->orderByDesc('id')
            ->limit(self::LIMIT_PER_CHECK)
            ->get()
            ->map(fn (Fine $f) => $this->issue($check, $severity, 'fine', $f->id, $f, $summary($f)));
    }

    private function issue(string $check, string $severity, string $referenceType, int $referenceId, Fine $fine, string $summary): array
    {
        return [
            'check'          => $check,
            'severity'       => $severity,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'student'        => $fine->user ? "{$fine->user->name} ({$fine->user->student_id})" : '—',
            'event'          => $fine->event?->title,
            'summary'        => $summary,
        ];
    }
}
