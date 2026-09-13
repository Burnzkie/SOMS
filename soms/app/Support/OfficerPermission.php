<?php

namespace App\Support;

use App\Models\User;

/**
 * Officer access control.
 *
 * v5.0: permission checks no longer derive from position tier
 * (Executive/Administrative/PublicRelations). Admin now grants each
 * officer an individual set of permissions via checkboxes on the
 * Officer Appointment screen; those checked keys are stored as JSON on
 * the officer's active OfficerPosition row (see the
 * add_permissions_to_officer_positions_table migration). ::can() reads
 * straight from that column — a President and a Secretary can end up
 * with completely different access if admin sets them that way.
 */
class OfficerPermission
{
    /**
     * Master list of permission keys admin can grant, with the label
     * shown next to each checkbox on the Officer Appointment screen.
     */
    const PERMISSIONS = [
        'manage_events'        => 'Manage Events (create/edit event setup, sessions, fine rules)',
        'manage_attendance'    => 'Manage Attendance',
        'manage_announcements' => 'Manage Announcements (publish/unpublish)',
        'draft_announcements'  => 'Draft Announcements',
        'manage_members'       => 'Manage Members',
        'manage_calendar'      => 'Manage Calendar',
        'view_reports'         => 'View Reports',
        'view_dashboard'       => 'View Officer Dashboard',
        'view_calendar'        => 'View Calendar',
    ];

    public static function isTreasurer(User $user): bool
    {
        return $user->activeOfficerPosition?->position_title === 'Treasurer';
    }

    /**
     * True if the user's active officer position has been explicitly
     * granted this permission. No active position (never appointed,
     * position expired, or revoked) always resolves to false — there is
     * no implicit/default access for any position title.
     */
    public static function can(User $user, string $permission): bool
    {
        $permissions = $user->activeOfficerPosition?->permissions ?? [];

        return in_array($permission, $permissions, true);
    }
}
