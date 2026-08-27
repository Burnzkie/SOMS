<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OfficerPosition;

/**
 * GET /admin/system-health (web) and /api/v1/admin/system-health (API).
 * See 09-Admin-Dashboard.md, Health Checks — Expanded health check beyond /ping.
 *
 * /ping only confirms the app process is alive. This authenticated
 * admin-only endpoint covers the two failure modes specific to this
 * stack: Clever Cloud's connection cap (via the queue-worker heartbeat)
 * and Render's free-tier queue reliability.
 *
 * The same controller/method serves both the web and API routes — the
 * response shape is identical JSON either way, so there's no reason to
 * duplicate this logic into two classes.
 */
class SystemHealthController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $lastFineJobRun = ActivityLog::where('action', 'fine_issuance_job_ran')->latest()->first();
        $queueStale = !$lastFineJobRun || $lastFineJobRun->created_at->diffInHours(now()) > 2;

        $logChainOk = ActivityLog::verifyChainIntegrity();

        $activeTreasurer = OfficerPosition::where('position_title', 'Treasurer')
            ->where('is_active', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'queueStale'        => $queueStale,
                'logChainOk'        => $logChainOk,
                'treasurerActive'   => $activeTreasurer,
                'lastFineJobRunAt'  => $lastFineJobRun?->created_at?->toIso8601String(),
                'dbConnectionsNote' => 'Clever Cloud cap: 5; pooled max: 4 (see 03-Auth-Security.md §20.9)',
            ],
        ]);
    }
}
