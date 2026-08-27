<?php

namespace App\Policies;

use App\Models\OfficerPosition;
use App\Models\User;

class OfficerPositionPolicy
{
    /**
     * Appoint a student to an officer position — Admin only.
     * See 04-Officer-Permissions-Members.md — Officer Appointment workflow.
     */
    public function appoint(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Revoke an active officer back to student — Admin only.
     */
    public function revoke(User $user, OfficerPosition $position): bool
    {
        return $user->role === 'admin';
    }
}