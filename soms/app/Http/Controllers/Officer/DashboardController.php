<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Fine;
use App\Models\Organization;
use App\Models\User;
use App\Support\OfficerPermission;

class DashboardController extends Controller
{
    protected function organizationId(): ?int
    {
        $user = auth()->user();

        return $user->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    /**
     * Officer landing page — shows the officer's position, permission-gated
     * quick links, and (new) an at-a-glance overview: live counts, the
     * next upcoming event (with cover image, if one was uploaded), and
     * the latest announcement. Counts/feature content are read-only
     * summaries; the permission-gated quick-links panel below still
     * governs what the officer can actually click into, per
     * 04-Officer-Permissions-Members.md.
     */
    public function index()
    {
        $user = auth()->user();
        $position = $user->activeOfficerPosition;
        $orgId = $this->organizationId();

        $permissions = [
            'manage_events'        => OfficerPermission::can($user, 'manage_events'),
            'manage_attendance'    => OfficerPermission::can($user, 'manage_attendance'),
            'manage_announcements' => OfficerPermission::can($user, 'manage_announcements'),
            'draft_announcements'  => OfficerPermission::can($user, 'draft_announcements'),
            'manage_members'       => OfficerPermission::can($user, 'manage_members'),
            'manage_calendar'      => OfficerPermission::can($user, 'manage_calendar'),
            'view_calendar'        => OfficerPermission::can($user, 'view_calendar'),
            'view_reports'         => OfficerPermission::can($user, 'view_reports'),
        ];

        $isTreasurer = OfficerPermission::isTreasurer($user);

        $stats = [
            'upcoming_events' => Event::where('organization_id', $orgId)
                ->where('date_end', '>=', now()->toDateString())
                ->count(),
            'active_members' => User::approved()
                ->whereHas('organizationMemberships', fn ($q) => $q->where('organization_id', $orgId))
                ->count(),
            'pending_fines' => Fine::where('status', 'unpaid')
                ->whereHas('event', fn ($q) => $q->where('organization_id', $orgId))
                ->count(),
            'new_announcements' => Announcement::where('organization_id', $orgId)
                ->where('is_published', true)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        $upcomingEvent = Event::where('organization_id', $orgId)
            ->where('is_published', true)
            ->where('date_end', '>=', now()->toDateString())
            ->orderBy('date_start')
            ->first();

        $latestAnnouncement = Announcement::where('organization_id', $orgId)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->first();

        return view('officer.dashboard', [
            'position'           => $position,
            'permissions'        => $permissions,
            'isTreasurer'        => $isTreasurer,
            'stats'              => $stats,
            'upcomingEvent'      => $upcomingEvent,
            'latestAnnouncement' => $latestAnnouncement,
        ]);
    }
}
