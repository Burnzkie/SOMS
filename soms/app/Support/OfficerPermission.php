<?php

namespace App\Support;

use App\Models\User;

class OfficerPermission
{
    const TIERS = [
        'Executive'       => ['President', 'Vice President'],
        'Administrative'  => ['Secretary', 'Treasurer', 'Auditor'],
        'PublicRelations' => ['Public Relations Officer'],
    ];

    const PERMISSIONS = [
        'manage_events'        => ['Executive', 'Administrative'],
        'manage_attendance'    => ['Executive', 'Administrative'],
        'manage_announcements' => ['Executive'],
        'draft_announcements'  => ['Executive', 'Administrative', 'PublicRelations'],
        'manage_members'       => ['Executive', 'Administrative'],
        'manage_calendar'      => ['Executive', 'Administrative'],
        'view_reports'         => ['Executive', 'Administrative'],
        'view_dashboard'       => ['Executive', 'Administrative', 'PublicRelations'],
        'view_calendar'        => ['Executive', 'Administrative', 'PublicRelations'],
    ];

    public static function tier(User $user): ?string
    {
        $position = $user->activeOfficerPosition?->position_title;
        foreach (self::TIERS as $tier => $positions) {
            if (in_array($position, $positions)) return $tier;
        }
        // No active officer position (never appointed, position expired, or
        // revoked) must resolve to no tier — not a silent fallback to
        // PublicRelations. can() already treats null correctly since
        // in_array(null, PERMISSIONS[...]) is false, but this was
        // previously returning the string 'PublicRelations' for anyone
        // with no position at all, granting draft_announcements,
        // view_dashboard, and view_calendar to accounts that should have
        // zero officer-tier access — most notably an officer whose
        // position was revoked (see the officer-revocation web UI gap).
        return null;
    }

    public static function isTreasurer(User $user): bool
    {
        return $user->activeOfficerPosition?->position_title === 'Treasurer';
    }

    public static function can(User $user, string $permission): bool
    {
        $tier = self::tier($user);
        return in_array($tier, self::PERMISSIONS[$permission] ?? []);
    }
}