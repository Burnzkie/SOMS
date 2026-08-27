<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        $upcomingEvents = Event::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->where('date_end', '>=', now()->toDateString())
            ->orderBy('date_start')
            ->take(3)
            ->get();

        $recentAnnouncements = Announcement::whereIn('organization_id', $orgIds)
            ->where('is_published', true)
            ->latest()
            ->take(3)
            ->get();

        $unpaidFinesCount = $user->fines()->where('status', 'unpaid')->count();
        $unpaidFinesAmount = $user->fines()->where('status', 'unpaid')->sum('amount');

        return view('student.dashboard', [
            'upcomingEvents'      => $upcomingEvents,
            'recentAnnouncements' => $recentAnnouncements,
            'unpaidFinesCount'    => $unpaidFinesCount,
            'unpaidFinesAmount'   => $unpaidFinesAmount,
        ]);
    }
}
