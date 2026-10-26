<?php
// app/Http/Controllers/Admin/AuditFindingController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\InvalidFindingTransition;
use App\Models\AuditFinding;
use App\Services\AuditFindingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin side of audit findings: read what the Auditor reported, then
 * acknowledge it or resolve it with a note on what was done. The Admin
 * decides the outcome; the Auditor can reopen it if the problem remains.
 */
class AuditFindingController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $severity = $request->input('severity');

        $findings = AuditFinding::with('reporter:id,name')
            ->when(in_array($status, AuditFinding::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when(in_array($severity, AuditFinding::SEVERITIES, true), fn ($q) => $q->where('severity', $severity))
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();

        $activeCount = AuditFinding::active()->count();

        return view('admin.audit-findings.index', compact('findings', 'activeCount'));
    }

    public function show(AuditFinding $finding)
    {
        $finding->load('reporter:id,name', 'resolver:id,name');

        return view('admin.audit-findings.show', compact('finding'));
    }

    /** Acknowledge (received, being handled) or resolve (with what was done). */
    public function respond(Request $request, AuditFinding $finding, AuditFindingService $findings)
    {
        $data = $request->validate([
            'action'          => ['required', Rule::in(['acknowledge', 'resolve'])],
            'resolution_note' => 'required_if:action,resolve|nullable|string|min:5|max:1000',
        ]);

        try {
            $findings->respond($finding, $data['action'], $data['resolution_note'] ?? null, auth()->id());
        } catch (InvalidFindingTransition $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('status', $data['action'] === 'acknowledge' ? 'Finding acknowledged.' : 'Finding resolved.');
    }
}
