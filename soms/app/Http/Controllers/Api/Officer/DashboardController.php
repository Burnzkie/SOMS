<?php
// app/Http/Controllers/Api/Officer/DashboardController.php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Mobile-facing counterpart to Officer\DashboardController's stats block.
 * Only ships the one figure the mobile dashboard can't derive from data
 * it already fetches for other tabs (Events/Fines/Announcements lists) —
 * organization member count, since officers have no /officer/members
 * list endpoint on either platform (member management is Admin-only;
 * see resources/views/partials/officer-nav.blade.php).
 *
 * Also lists the permissions this officer holds, because the login response
 * carries no position or permissions: the app uses it to show the Audit
 * workspace only to officers who have an audit permission, instead of
 * showing it to everyone and answering 403.
 */
class DashboardController extends Controller
{
    protected function organizationId(Request $request): ?int
    {
        return $request->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    /** @return array<int, string> keys of the permissions this officer currently holds */
    private function heldPermissions(User $user): array
    {
        return collect(array_keys(OfficerPermission::PERMISSIONS))
            ->filter(fn (string $permission) => OfficerPermission::can($user, $permission))
            ->values()
            ->all();
    }

    public function summary(Request $request)
    {
        $orgId = $this->organizationId($request);

        $activeMembers = User::approved()
            ->whereHas('organizationMemberships', fn ($q) => $q->where('organization_id', $orgId))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'active_members' => $activeMembers,
                'permissions'    => $this->heldPermissions($request->user()),
            ],
        ]);
    }
}
