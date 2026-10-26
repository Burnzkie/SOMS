<?php
// app/Http/Controllers/Api/Officer/AuditController.php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Concerns\RequiresOfficerPermission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditFinding;
use App\Models\EventAttendance;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Mobile counterpart of the Auditor workspace: the same read-only checks
 * and findings as the web pages, as JSON. Same permissions per endpoint.
 */
class AuditController extends Controller
{
    use RequiresOfficerPermission;

    public function __construct(private AuditService $audit)
    {
    }

    /** GET /api/v1/officer/audit/summary */
    public function summary()
    {
        $this->requirePermission('view_reports');

        return response()->json(['success' => true, 'data' => $this->audit->summary()]);
    }

    /** GET /api/v1/officer/audit/fines?status=&q= */
    public function fines(Request $request)
    {
        $this->requirePermission('view_fines');

        $status = $request->input('status');
        $search = trim((string) $request->input('q'));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        $fines = Fine::with(['user:id,name,student_id', 'event:id,title', 'clearedBy:id,name'])
            ->when(in_array($status, ['unpaid', 'paid', 'waived'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('receipt_number', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('student_id', 'like', $like))))
            ->latest('issued_at')->latest('id')
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $fines->through(fn (Fine $f) => [
            'id'             => $f->id,
            'student_name'   => $f->user?->name,
            'student_id'     => $f->user?->student_id,
            'event'          => $f->event?->title,
            'violation_type' => $f->violation_type,
            'amount'         => (float) $f->amount,
            'status'         => $f->status,
            'issued_at'      => $f->issued_at?->toIso8601String(),
            'settled_by'     => $f->clearedBy?->name,
            'settled_at'     => $f->cleared_at?->toIso8601String(),
            'receipt_number' => $f->receipt_number,
            'reason'         => $f->reason,
        ])]);
    }

    /** GET /api/v1/officer/audit/discrepancies */
    public function discrepancies()
    {
        $this->requirePermission('view_reports');

        return response()->json(['success' => true, 'data' => $this->audit->discrepancies()->map(fn (array $i) => $i + [
            'check_label' => AuditService::CHECK_LABELS[$i['check']] ?? $i['check'],
        ])->values()]);
    }

    /**
     * GET /api/v1/officer/audit/attendance?page=
     * Per-session totals (paged), plus the flagged-scan history and manual
     * overrides. Read-only.
     */
    public function attendance()
    {
        $this->requirePermission('view_attendance');

        ['sessions' => $sessions, 'counts' => $counts, 'fineCounts' => $fineCounts] = $this->audit->attendanceBySession();
        $iso = fn ($date) => $date?->toIso8601String();

        return response()->json(['success' => true, 'data' => [
            'sessions' => $sessions->through(fn (EventSession $s) => [
                'session_id'      => $s->id,
                'event'           => $s->eventDay->event->title,
                'session'         => $s->label,
                'timein_start'    => $iso($s->timein_start),
                'timein_end'      => $iso($s->timein_end),
                'present'         => (int) ($counts[$s->id]->present_count ?? 0),
                'pending_flagged' => (int) ($counts[$s->id]->pending_count ?? 0),
                'approved'        => (int) ($counts[$s->id]->approved_count ?? 0),
                'rejected'        => (int) ($counts[$s->id]->rejected_count ?? 0),
                'overrides'       => (int) ($counts[$s->id]->override_count ?? 0),
                'fines'           => (int) ($fineCounts[$s->id] ?? 0),
                'fines_issued'    => (bool) $s->fines_issued,
            ]),
            'flagged_history' => $this->audit->flaggedScanHistory()->map(fn (EventAttendance $a) => [
                'attendance_id'     => $a->id,
                'student_name'      => $a->user->name,
                'student_id'        => $a->user->student_id,
                'event'             => $a->eventSession->eventDay->event->title,
                'session'           => $a->eventSession->label,
                'scan_type'         => $a->scan_type,
                'device_scanned_at' => $iso($a->device_scanned_at),
                'scanned_at'        => $iso($a->scanned_at),
                'decision'          => $a->review_decision,
                'reviewed_by'       => $a->reviewer?->name,
                'reviewed_at'       => $iso($a->reviewed_at),
            ])->values(),
            'overrides' => $this->audit->manualOverrides()->map(fn (EventAttendance $a) => [
                'attendance_id' => $a->id,
                'student_name'  => $a->user->name,
                'student_id'    => $a->user->student_id,
                'event'         => $a->eventSession->eventDay->event->title,
                'session'       => $a->eventSession->label,
                'scan_type'     => $a->scan_type,
                'marked_by'     => $a->markedBy?->name,
                'scanned_at'    => $iso($a->scanned_at),
                'reason'        => $a->override_reason,
            ])->values(),
        ]]);
    }

    /**
     * GET /api/v1/officer/audit/activity-log?action=&from=&to=&page=
     * Read-only. Only the masked IP is ever returned, never the stored one.
     */
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
            ->paginate(25);

        return response()->json(['success' => true, 'data' => [
            'logs' => $logs->through(fn (ActivityLog $log) => [
                'id'         => $log->id,
                'created_at' => $log->created_at->toIso8601String(),
                'user'       => $log->user->name ?? 'System',
                'action'     => $log->action,
                'record'     => $log->model_type ? class_basename($log->model_type) . ' #' . $log->model_id : null,
                'details'    => $log->payload ? Str::limit(json_encode($log->payload), 120) : null,
                'ip'         => $log->ip_address_display,
            ]),
            'actions'  => $this->audit->logActions(),
            'chain_ok' => $this->audit->logChainOk(),
        ]]);
    }

    /** GET /api/v1/officer/audit/findings?status= */
    public function findings(Request $request)
    {
        $this->requirePermission('manage_audit_findings', 'view_reports');

        $status = $request->input('status');

        $findings = AuditFinding::with('reporter:id,name', 'resolver:id,name')
            ->when(in_array($status, AuditFinding::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest('reported_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $findings]);
    }

    /** POST /api/v1/officer/audit/findings */
    public function storeFinding(Request $request)
    {
        $this->requirePermission('manage_audit_findings');

        $data = $request->validate([
            'reference_type' => ['required', Rule::in(AuditFinding::REFERENCE_TYPES)],
            'reference_id'   => 'nullable|integer|min:1',
            'title'          => 'required|string|max:160',
            'issue'          => 'required|string|max:2000',
            'evidence'       => 'nullable|string|max:2000',
            'severity'       => ['required', Rule::in(AuditFinding::SEVERITIES)],
        ]);

        $finding = AuditFinding::create($data + [
            'organization_id' => auth()->user()->activeOfficerPosition?->organization_id ?? Organization::query()->value('id'),
            'status'          => 'open',
            'reported_by'     => auth()->id(),
            'reported_at'     => now(),
        ]);

        ActivityLog::record(auth()->id(), 'audit_finding_created', AuditFinding::class, $finding->id, [
            'reference' => $finding->reference_type . ':' . $finding->reference_id, 'severity' => $finding->severity,
        ]);

        return response()->json(['success' => true, 'message' => 'Finding recorded.', 'data' => $finding], 201);
    }
}
