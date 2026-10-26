<?php
// app/Http/Controllers/Api/Admin/AuditFindingController.php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\InvalidFindingTransition;
use App\Http\Controllers\Controller;
use App\Models\AuditFinding;
use App\Services\AuditFindingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile counterpart of Admin\AuditFindingController: the Admin reads what
 * the Auditor reported and acknowledges or resolves it. Same rules, via
 * AuditFindingService.
 */
class AuditFindingController extends Controller
{
    /** GET /api/v1/admin/audit-findings?status=&severity=&page= */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $severity = $request->input('severity');

        $findings = AuditFinding::with('reporter:id,name', 'resolver:id,name')
            ->when(in_array($status, AuditFinding::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when(in_array($severity, AuditFinding::SEVERITIES, true), fn ($q) => $q->where('severity', $severity))
            ->latest('reported_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $findings,
            'meta'    => ['active_count' => AuditFinding::active()->count()],
        ]);
    }

    /** POST /api/v1/admin/audit-findings/{finding}/respond */
    public function respond(Request $request, AuditFinding $finding, AuditFindingService $findings)
    {
        $data = $request->validate([
            'action'          => ['required', Rule::in(['acknowledge', 'resolve'])],
            'resolution_note' => 'required_if:action,resolve|nullable|string|min:5|max:1000',
        ]);

        try {
            $findings->respond($finding, $data['action'], $data['resolution_note'] ?? null, auth()->id());
        } catch (InvalidFindingTransition $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $data['action'] === 'acknowledge' ? 'Finding acknowledged.' : 'Finding resolved.',
            'data'    => $finding->fresh(['reporter:id,name', 'resolver:id,name']),
        ]);
    }
}
