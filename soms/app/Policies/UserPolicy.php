<?php

namespace App\Policies;

use App\Models\User;

/**
 * Avatar authorization — one gate per CRUD verb (App\Http\Controllers\Avatar-
 * Controller and Api\AvatarController), all sharing the same rule: a user
 * may only act on their OWN avatar, regardless of role. Available to every
 * authenticated role (admin, officer, student) — not admin-only.
 */
class UserPolicy
{
    public function viewAvatar(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function createAvatar(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function updateAvatar(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function deleteAvatar(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }
}
