<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Support\OfficerPermission;

class DashboardController extends Controller
{
    /**
     * Officer landing page — shows the officer's position/tier and which
     * permission-gated modules they can access, per 04-Officer-Permissions-Members.md.
     * Quick-links panel only ever links to routes the officer's tier
     * actually has access to.
     */
    public function index()
    {
        $user = auth()->user();
        $position = $user->activeOfficerPosition;
        $tier = OfficerPermission::tier($user);

        // If $position is null (revoked/expired/never assigned), $tier is
        // now correctly null too. The view already guards the tier badge
        // with @if($position), and every entry in $permissions below
        // resolves to false via OfficerPermission::can() when $tier is
        // null — so an officer-role account with no active position lands
        // on a dashboard with zero permissions granted, rather than
        // silently getting PublicRelations-tier access.

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

        // Fines are a position-level exception, not tier-level — see 05-Attendance-Fines.md Part D.
        $isTreasurer = OfficerPermission::isTreasurer($user);

        return view('officer.dashboard', [
            'position'    => $position,
            'tier'        => $tier,
            'permissions' => $permissions,
            'isTreasurer' => $isTreasurer,
        ]);
    }
}
