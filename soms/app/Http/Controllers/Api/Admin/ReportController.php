<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Support\Facades\DB;

/**
 * Mobile counterpart to Http\Controllers\Admin\ReportController.
 * See 09-Admin-Dashboard.md, Reports (Treasurer-only PDF) — this JSON
 * endpoint provides the same paid/waived-distinguished data; PDF
 * generation itself remains web-only (mobile displays the figures, it
 * doesn't render the printable report).
 */
class ReportController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Fine::class);

        $paid = Fine::where('status', 'paid')->with('user')->get();
        $waived = Fine::where('status', 'waived')->with('user')->get();

        $studentsWithFines = Fine::with('user')
            ->select('user_id', DB::raw('count(*) as fine_count'))
            ->selectRaw("sum(case when status = 'paid' then amount else 0 end) as total_paid")
            ->selectRaw("sum(case when status = 'waived' then amount else 0 end) as total_waived")
            ->groupBy('user_id')
            ->get();

        return response()->json(['success' => true, 'data' => [
            'paid'               => $paid,
            'waived'             => $waived,
            'totalCollected'     => $paid->sum('amount'),
            'totalWaived'        => $waived->sum('amount'),
            'studentsWithFines'  => $studentsWithFines,
        ]]);
    }
}
