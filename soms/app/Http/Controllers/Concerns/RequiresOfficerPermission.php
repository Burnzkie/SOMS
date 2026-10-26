<?php
// app/Http/Controllers/Concerns/RequiresOfficerPermission.php

namespace App\Http\Controllers\Concerns;

use App\Support\OfficerPermission;

/**
 * One place for the "officer must hold at least one of these permissions"
 * check, shared by the web and API audit controllers. No implicit access:
 * an officer without an active position, or without the permission, gets 403.
 */
trait RequiresOfficerPermission
{
    protected function requirePermission(string ...$anyOf): void
    {
        $user = auth()->user();

        foreach ($anyOf as $permission) {
            if (OfficerPermission::can($user, $permission)) {
                return;
            }
        }

        abort(403);
    }
}
