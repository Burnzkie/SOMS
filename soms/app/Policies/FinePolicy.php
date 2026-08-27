<?php

namespace App\Policies;

use App\Models\Fine;
use App\Models\User;
use App\Support\OfficerPermission;


class FinePolicy
{
    /**
     * Treasurer + Admin can view the org-wide fines list.
     */
    public function viewAny(User $user): bool
    {
        return OfficerPermission::isTreasurer($user) || $user->role === 'admin';
    }

    /**
     * A student can view their own fine (read-only); Treasurer + Admin can view any fine.
     */
    public function view(User $user, Fine $fine): bool
    {
        return $user->id === $fine->user_id
            || OfficerPermission::isTreasurer($user)
            || $user->role === 'admin';
    }

    /**
     * Mark a fine paid (after in-person payment) — Treasurer + Admin only.
     */
    public function clear(User $user, Fine $fine): bool
    {
        return OfficerPermission::isTreasurer($user) || $user->role === 'admin';
    }

    /**
     * Waive a fine (no payment collected) — same authorization boundary as clear.
     */
    public function waive(User $user, Fine $fine): bool
    {
        return OfficerPermission::isTreasurer($user) || $user->role === 'admin';
    }
}