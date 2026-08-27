<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Models\OfficerPosition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Admin landing page — summary cards, pending approvals, treasurer/log-chain status.
     * See 09-Admin-Dashboard.md.
     */
    public function index()
    {
        $totalStudents = User::students()->approved()->count();
        $activeOfficers = User::officers()->count();
        $pendingApprovals = User::pending()->count();
        $totalUnpaidFines = Fine::unpaid()->sum('amount');

        $treasurerActive = OfficerPosition::where('position_title', 'Treasurer')
            ->where('is_active', true)
            ->exists();

        $logChainOk = ActivityLog::verifyChainIntegrity();

        // Queue-worker heartbeat -- see 09-Admin-Dashboard.md, Health Checks.
        // IssueSessionFinesJob logs fine_issuance_job_ran on every run
        // (success or no-op); no entry in >2 hours means the Render queue
        // worker likely isn't processing jobs.
        $lastFineJobRun = ActivityLog::where('action', 'fine_issuance_job_ran')->latest()->first();
        $queueStale = !$lastFineJobRun || $lastFineJobRun->created_at->diffInHours(now()) > 2;

        $recentPendingStudents = User::pending()->latest()->take(5)->get();

        return view('admin.dashboard', [
            'totalStudents'         => $totalStudents,
            'activeOfficers'        => $activeOfficers,
            'pendingApprovals'      => $pendingApprovals,
            'totalUnpaidFines'      => $totalUnpaidFines,
            'treasurerActive'       => $treasurerActive,
            'logChainOk'            => $logChainOk,
            'queueStale'            => $queueStale,
            'recentPendingStudents' => $recentPendingStudents,
        ]);
    }
}