<?php
// app/Http/Controllers/Officer/AuditFindingController.php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Concerns\RequiresOfficerPermission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditFinding;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Audit findings: the Auditor records what they found and tracks it until
 * the Admin resolves it. A finding never changes the record it is about, so
 * there is always a visible history of what was reported and who dealt with it.
 */
class AuditFindingController extends Controller
{
    use RequiresOfficerPermission;

    protected function organizationId(): ?int
    {
        return auth()->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index(Request $request)
    {
        $this->requirePermission('manage_audit_findings', 'view_reports');

        $status = $request->input('status');

        $findings = AuditFinding::with('reporter:id,name')
            ->when(in_array($status, AuditFinding::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();

        $canManage = \App\Support\OfficerPermission::can(auth()->user(), 'manage_audit_findings');

        return view('officer.audit.findings.index', compact('findings', 'canManage'));
    }

    public function create(Request $request)
    {
        $this->requirePermission('manage_audit_findings');

        return view('officer.audit.findings.create', [
            'referenceType' => $request->input('reference_type'),
            'referenceId'   => $request->input('reference_id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->requirePermission('manage_audit_findings');

        $data = $this->validated($request) + $request->validate([
            'reference_type' => ['required', Rule::in(AuditFinding::REFERENCE_TYPES)],
            'reference_id'   => 'nullable|integer|min:1',
        ]);

        $finding = AuditFinding::create($data + [
            'organization_id' => $this->organizationId(),
            'status'          => 'open',
            'reported_by'     => auth()->id(),
            'reported_at'     => now(),
        ]);

        ActivityLog::record(auth()->id(), 'audit_finding_created', AuditFinding::class, $finding->id, [
            'reference' => $finding->reference_type . ':' . $finding->reference_id, 'severity' => $finding->severity,
        ]);

        return redirect()->route('officer.audit.findings.show', $finding)->with('status', 'Finding recorded.');
    }

    /**
     * "Report as finding" from the discrepancy report. The text comes from
     * the server's own check, not the request, so a finding can only be
     * raised for a discrepancy that actually exists right now. If an earlier
     * finding for it is still active, that one is opened instead.
     */
    public function fromCheck(Request $request, AuditService $audit)
    {
        $this->requirePermission('manage_audit_findings');

        $data = $request->validate([
            'check'          => ['required', Rule::in(array_keys(AuditService::CHECK_LABELS))],
            'reference_type' => ['required', Rule::in(AuditFinding::REFERENCE_TYPES)],
            'reference_id'   => 'required|integer|min:1',
        ]);

        $existing = AuditFinding::active()
            ->where('check_type', $data['check'])
            ->where('reference_type', $data['reference_type'])
            ->where('reference_id', $data['reference_id'])
            ->first();

        if ($existing) {
            return redirect()->route('officer.audit.findings.show', $existing)->with('status', 'A finding for this is already open.');
        }

        $issue = $audit->discrepancies()->first(fn (array $i) => $i['check'] === $data['check']
            && $i['reference_type'] === $data['reference_type']
            && $i['reference_id'] === (int) $data['reference_id']);

        if (!$issue) {
            return redirect()->route('officer.audit.discrepancies')->with('status', 'That discrepancy no longer exists.');
        }

        $finding = AuditFinding::create([
            'organization_id' => $this->organizationId(),
            'reference_type'  => $issue['reference_type'],
            'reference_id'    => $issue['reference_id'],
            'check_type'      => $issue['check'],
            'title'           => AuditService::CHECK_LABELS[$issue['check']] . ' — ' . $issue['student'],
            'issue'           => $issue['summary'],
            'severity'        => $issue['severity'],
            'status'          => 'open',
            'reported_by'     => auth()->id(),
            'reported_at'     => now(),
        ]);

        ActivityLog::record(auth()->id(), 'audit_finding_created', AuditFinding::class, $finding->id, [
            'reference' => $finding->reference_type . ':' . $finding->reference_id, 'check' => $finding->check_type,
        ]);

        return redirect()->route('officer.audit.findings.show', $finding)->with('status', 'Finding recorded. Add evidence or notes below.');
    }

    public function show(AuditFinding $finding)
    {
        $this->requirePermission('manage_audit_findings', 'view_reports');

        $finding->load('reporter:id,name', 'resolver:id,name');
        $canManage = \App\Support\OfficerPermission::can(auth()->user(), 'manage_audit_findings');

        return view('officer.audit.findings.show', compact('finding', 'canManage'));
    }

    /** Edit title, issue, evidence and severity, only while the finding is still open. */
    public function update(Request $request, AuditFinding $finding)
    {
        $this->requirePermission('manage_audit_findings');
        abort_unless($finding->isEditable(), 422, 'Only an open finding can be edited.');

        $finding->update($this->validated($request));

        ActivityLog::record(auth()->id(), 'audit_finding_updated', AuditFinding::class, $finding->id);

        return back()->with('status', 'Finding updated.');
    }

    /** The Auditor withdraws a finding raised in error (needs a reason). */
    public function withdraw(Request $request, AuditFinding $finding)
    {
        $this->requirePermission('manage_audit_findings');
        abort_unless(in_array($finding->status, ['open', 'acknowledged'], true), 422, 'This finding is already closed.');

        $data = $request->validate(['resolution_note' => 'required|string|min:5|max:1000']);

        $finding->update([
            'status'          => 'dismissed',
            'resolution_note' => $data['resolution_note'],
            'resolved_by'     => auth()->id(),
            'resolved_at'     => now(),
        ]);

        ActivityLog::record(auth()->id(), 'audit_finding_dismissed', AuditFinding::class, $finding->id, ['note' => $data['resolution_note']]);

        return back()->with('status', 'Finding withdrawn.');
    }

    /** The Auditor reopens a finding whose problem is still there. */
    public function reopen(Request $request, AuditFinding $finding)
    {
        $this->requirePermission('manage_audit_findings');
        abort_unless(in_array($finding->status, ['resolved', 'dismissed'], true), 422, 'Only a closed finding can be reopened.');

        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        // The earlier outcome is kept in the activity log; the row goes back to open.
        ActivityLog::record(auth()->id(), 'audit_finding_reopened', AuditFinding::class, $finding->id, [
            'reason' => $data['reason'], 'previous_status' => $finding->status, 'previous_note' => $finding->resolution_note,
        ]);

        $finding->update(['status' => 'open', 'resolution_note' => null, 'resolved_by' => null, 'resolved_at' => null]);

        return back()->with('status', 'Finding reopened.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'    => 'required|string|max:160',
            'issue'    => 'required|string|max:2000',
            'evidence' => 'nullable|string|max:2000',
            'severity' => ['required', Rule::in(AuditFinding::SEVERITIES)],
        ]);
    }
}
