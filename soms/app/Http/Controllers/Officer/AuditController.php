<?php
// app/Http/Controllers/Officer/AuditController.php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Concerns\RequiresOfficerPermission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditFinding;
use App\Models\Event;
use App\Models\Fine;
use App\Services\AuditService;
use App\Support\CsvExport;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Auditor workspace: read-only views over fines, payments, attendance and
 * the activity log, plus the discrepancy report and its CSV exports.
 * Nothing here edits a record; problems are reported as audit findings
 * (Officer\AuditFindingController). Each page has its own permission.
 */
class AuditController extends Controller
{
    use RequiresOfficerPermission;

    public function __construct(private AuditService $audit)
    {
    }

    /** Totals, discrepancy counts and the latest findings. */
    public function dashboard()
    {
        $this->requirePermission('view_reports');

        $summary = $this->audit->summary();

        // The full check is heavier than the rest of the dashboard, so the
        // counts are cached briefly. The discrepancy report itself is always live.
        $discrepancyCounts = Cache::remember(
            'audit.discrepancy-counts',
            120,
            fn () => $this->audit->discrepancies()->countBy('severity')->all()
        );

        $recentFindings = AuditFinding::with('reporter:id,name')->latest('reported_at')->limit(5)->get();

        return view('officer.audit.dashboard', compact('summary', 'discrepancyCounts', 'recentFindings'));
    }

    /** Every fine with its status, receipt and who settled it. Read-only. */
    public function fines(Request $request)
    {
        $this->requirePermission('view_fines');

        $fines = $this->filteredFines($request)
            ->with(['user:id,name,student_id', 'event:id,title', 'clearedBy:id,name'])
            ->latest('issued_at')->latest('id')
            ->paginate(25)
            ->withQueryString();

        $events = Event::orderByDesc('date_start')->get(['id', 'title']);

        $canExport = OfficerPermission::can(auth()->user(), 'view_reports');

        return view('officer.audit.fines', compact('fines', 'events', 'canExport'));
    }

    /** Session-by-session attendance totals, flagged-scan history and manual overrides. */
    public function attendance()
    {
        $this->requirePermission('view_attendance');

        ['sessions' => $sessions, 'counts' => $counts, 'fineCounts' => $fineCounts] = $this->audit->attendanceBySession();
        $flaggedHistory = $this->audit->flaggedScanHistory();
        $overrides = $this->audit->manualOverrides();

        return view('officer.audit.attendance', compact('sessions', 'counts', 'fineCounts', 'flaggedHistory', 'overrides'));
    }

    /** Live discrepancy report, with a link to raise a finding for each item. */
    public function discrepancies()
    {
        $this->requirePermission('view_reports');

        $issues = $this->audit->discrepancies();

        // Issues that already have an open finding show a link instead of a button.
        $findingFor = AuditFinding::active()
            ->whereNotNull('check_type')
            ->get(['id', 'check_type', 'reference_type', 'reference_id'])
            ->mapWithKeys(fn ($f) => [$f->check_type . '|' . $f->reference_type . '|' . $f->reference_id => $f->id]);

        return view('officer.audit.discrepancies', compact('issues', 'findingFor'));
    }

    /** Read-only activity log with masked IP addresses. */
    public function activityLog(Request $request)
    {
        $this->requirePermission('view_audit_logs');

        $filters = $request->validate([
            'action' => 'nullable|string|max:100',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
        ]);

        $logs = $this->audit->activityLogs($filters['action'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null)
            ->with('user:id,name')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $actions = $this->audit->logActions();
        $chainOk = $this->audit->logChainOk();

        return view('officer.audit.activity-log', compact('logs', 'actions', 'chainOk'));
    }

    /** CSV of the fines list (same filters as the page). Logged. */
    public function exportFines(Request $request)
    {
        $this->requirePermission('view_reports');

        ActivityLog::record(auth()->id(), 'audit_export', null, null, ['type' => 'fines']);

        $rows = $this->filteredFines($request)
            ->with(['user:id,name,student_id', 'event:id,title', 'clearedBy:id,name'])
            // lazyById (not cursor) so the eager loads above apply: one query per
            // 500 fines instead of three per fine.
            ->lazyById(500)
            ->map(fn (Fine $f) => [
                $f->id,
                $f->user?->student_id,
                $f->user?->name,
                $f->event?->title,
                $f->violation_type,
                number_format((float) $f->amount, 2, '.', ''),
                $f->status,
                $f->issued_at?->format('Y-m-d H:i'),
                $f->clearedBy?->name,
                $f->cleared_at?->format('Y-m-d H:i'),
                $f->receipt_number,
                $f->reason,
            ]);

        return CsvExport::download(
            'fines-audit-' . now()->format('Ymd-His') . '.csv',
            ['Fine ID', 'Student ID', 'Student', 'Event', 'Violation', 'Amount', 'Status', 'Issued', 'Settled by', 'Settled at', 'Receipt no.', 'Reason'],
            $rows
        );
    }

    /** CSV of the discrepancy report. Logged. */
    public function exportDiscrepancies()
    {
        $this->requirePermission('view_reports');

        ActivityLog::record(auth()->id(), 'audit_export', null, null, ['type' => 'discrepancies']);

        $rows = $this->audit->discrepancies()->map(fn (array $i) => [
            $i['severity'],
            AuditService::CHECK_LABELS[$i['check']] ?? $i['check'],
            $i['reference_type'] . ' #' . $i['reference_id'],
            $i['student'],
            $i['event'],
            $i['summary'],
        ]);

        return CsvExport::download(
            'discrepancies-' . now()->format('Ymd-His') . '.csv',
            ['Severity', 'Check', 'Reference', 'Student', 'Event', 'Details'],
            $rows
        );
    }

    /** Shared filters for the fines page and its export. */
    private function filteredFines(Request $request)
    {
        $status = $request->input('status');
        $search = trim((string) $request->input('q'));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        return Fine::query()
            ->when(in_array($status, ['unpaid', 'paid', 'waived'], true), fn ($q) => $q->where('status', $status))
            ->when($request->input('event_id'), fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('receipt_number', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('student_id', 'like', $like))));
    }
}
