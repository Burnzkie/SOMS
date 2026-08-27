<?php

namespace App\Policies;

use App\Models\AttendanceDelegate;
use App\Models\EventSession;
use App\Models\User;
use App\Support\OfficerPermission;

class AttendanceSessionPolicy
{
    /**
     * Scan (mark attendance) for this session — Executive/Administrative officers
     * (manage_attendance permission — see 04-Officer-Permissions-Members.md), or a
     * delegate specifically assigned to this session (attendance_delegates).
     */
    public function scan(User $user, EventSession $session): bool
    {
        return OfficerPermission::can($user, 'manage_attendance') || $this->isDelegate($user, $session);
    }

    /**
     * "Close Session" — manually trigger fine issuance for this session.
     * Executive/Administrative officers only; delegates cannot close a session,
     * only scan/override within it.
     */
    public function close(User $user, EventSession $session): bool
    {
        return OfficerPermission::can($user, 'manage_attendance');
    }

    /**
     * Manual attendance override (fallback, requires live password re-auth
     * per 03-Auth-Security.md §20.2) — same eligibility as scanning.
     */
    public function override(User $user, EventSession $session): bool
    {
        return OfficerPermission::can($user, 'manage_attendance') || $this->isDelegate($user, $session);
    }

    /**
     * Whether $user is the attendance delegate assigned to this specific session.
     * Delegate access is scoped per-session and revoked immediately on reassignment
     * (no caching of grants) — see 05-Attendance-Fines.md.
     */
    protected function isDelegate(User $user, EventSession $session): bool
    {
        return AttendanceDelegate::where('event_session_id', $session->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}