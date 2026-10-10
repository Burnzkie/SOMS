<?php
// app/Http/Controllers/Admin/AnalyticsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDailyHit;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index()
    {
        $since30 = Carbon::today()->subDays(29); // inclusive of today = 30 days

        $totalToday = AnalyticsDailyHit::whereDate('date', Carbon::today())->sum('hits');
        $totalLast7 = AnalyticsDailyHit::whereDate('date', '>=', Carbon::today()->subDays(6))->sum('hits');
        $totalLast30 = AnalyticsDailyHit::whereDate('date', '>=', $since30)->sum('hits');

        // Daily totals for the last 30 days, split by platform, for the table.
        $dailyRows = AnalyticsDailyHit::whereDate('date', '>=', $since30)
            ->selectRaw('date, platform, SUM(hits) as hits')
            ->groupBy('date', 'platform')
            ->orderByDesc('date')
            ->get()
            ->groupBy(fn ($row) => $row->date->toDateString());

        $dailyBreakdown = [];
        foreach ($dailyRows as $date => $rows) {
            $dailyBreakdown[$date] = [
                'web' => (int) ($rows->firstWhere('platform', 'web')->hits ?? 0),
                'mobile' => (int) ($rows->firstWhere('platform', 'mobile')->hits ?? 0),
            ];
        }

        // Most-visited routes in the last 30 days, across both platforms.
        $topRoutes = AnalyticsDailyHit::whereDate('date', '>=', $since30)
            ->selectRaw('route_name, SUM(hits) as hits')
            ->groupBy('route_name')
            ->orderByDesc('hits')
            ->take(15)
            ->get();

        return view('admin.analytics', [
            'totalToday' => $totalToday,
            'totalLast7' => $totalLast7,
            'totalLast30' => $totalLast30,
            'dailyBreakdown' => $dailyBreakdown,
            'topRoutes' => $topRoutes,
        ]);
    }
}
