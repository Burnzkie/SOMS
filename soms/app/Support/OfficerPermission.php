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
        'view_reports'         => 'View Reports (audit dashboard, discrepancy report, CSV export)',
        'view_attendance'      => 'View Attendance Records (read-only, incl. flagged-scan history)',
        'view_fines'           => 'View Fines & Payments (read-only)',
        'view_audit_logs'      => 'View Audit Logs (read-only)',
        'manage_audit_findings' => 'Record & Track Audit Findings',
        'view_dashboard'       => 'View Officer Dashboard',
        'view_calendar'        => 'View Calendar',
    ];

    /**
     * Recommended starting access for each position: the SOMS role matrix
     * (President, Vice President, Secretary, Treasurer, Auditor, PIO)
     * expressed in the permission keys above. Applied when an officer is
     * appointed without an explicit permission list, and offered as
     * "Apply recommended" on the Permissions screens. The Admin can always
     * change an individual officer's access afterwards.
     *
     * How the matrix maps, including what it cannot yet express:
     *  - "Full" events = manage_events. "View" attendance / fines = the
     *    read-only view_attendance / view_fines. "Approve" announcements =
     *    manage_announcements (publish); "Limited" = draft_announcements.
     *  - Financial and audit reports and findings = view_reports. Only the
     *    Auditor can record findings (manage_audit_findings); the Admin
     *    resolves them. Treasurer fine management comes from the Treasurer
     *    title itself, not from a permission.
     *  - Secretary also gets manage_events although the matrix says "View":
     *    the web event pages are gated entirely by manage_events and the
     *    scan station is opened from them, so without it the Secretary
     *    could not reach attendance. A read-only events permission would fix
     *    that properly.
     *  - Not represented: meeting minutes/documents and officer task
     *    monitoring (those modules don't exist), and manage_members, which
     *    no screen checks.
     */
    const PRESETS = [
        'President' => [
            'view_dashboard', 'view_calendar', 'manage_calendar', 'manage_events',
            'view_attendance', 'view_fines', 'view_reports',
            'manage_announcements', 'draft_announcements',
        ],
        'Vice President' => [
            'view_dashboard', 'view_calendar', 'manage_calendar', 'manage_events',
            'view_attendance', 'view_fines', 'view_reports',
            'draft_announcements',
        ],
        'Secretary' => [
            'view_dashboard', 'view_calendar', 'manage_calendar', 'manage_events',
            'manage_attendance', 'view_attendance',
        ],
        'Treasurer' => [
            'view_dashboard', 'view_calendar', 'view_attendance', 'view_reports',
        ],
        'Auditor' => [
            'view_dashboard', 'view_calendar', 'view_attendance', 'view_fines', 'view_reports',
            'view_audit_logs', 'manage_audit_findings',
        ],
        'Public Relations Officer' => [
            'view_dashboard', 'view_calendar', 'manage_calendar',
            'manage_announcements', 'draft_announcements',
        ],
    ];

    /** The recommended permission keys for a position title (none for an unknown title). */
    public static function presetFor(string $positionTitle): array
    {
        return self::PRESETS[$positionTitle] ?? [];
    }

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
