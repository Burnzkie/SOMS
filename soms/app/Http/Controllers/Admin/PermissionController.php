<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OfficerPosition;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Standalone "Permissions" area in the admin sidebar — separate from
 * Officer Appointment. List every currently-appointed officer, click
 * one, check the boxes for what they can access. Kept apart from
 * OfficerAppointmentController on purpose: appointing/revoking an
 * officer and deciding what they can access are different jobs, and
 * mixing checkboxes into the appointment form made that page noisy.
 */
class PermissionController extends Controller
{
    /**
     * List of active officer positions to choose from. Ordered by
     * position title for a stable, scannable list.
     */
    public function index()
    {
        $this->authorize('appoint', OfficerPosition::class);

        $officers = OfficerPosition::where('is_active', true)
            ->with('user')
            ->orderBy('position_title')
            ->get();

        return view('admin.permissions.index', ['officers' => $officers]);
    }

    /**
     * Checkbox form for a single officer's permissions.
     */
    public function edit(OfficerPosition $position)
    {
        $this->authorize('appoint', OfficerPosition::class);

        abort_unless($position->is_active, 404);

        return view('admin.permissions.edit', [
            'position'             => $position->load('user'),
            'availablePermissions' => OfficerPermission::PERMISSIONS,
        ]);
    }

    /**
     * Save the checked permissions for one officer. Doesn't touch
     * appointment state — admin can change what an officer can access
     * mid-term without revoking and re-appointing them.
     */
    public function update(Request $request, OfficerPosition $position)
    {
        $this->authorize('appoint', OfficerPosition::class);

        abort_unless($position->is_active, 422, 'This officer position is not currently active.');

        $data = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'in:' . implode(',', array_keys(OfficerPermission::PERMISSIONS)),
        ]);

        $position->update(['permissions' => array_values($data['permissions'] ?? [])]);

        ActivityLog::record(auth()->id(), 'officer_permissions_updated', OfficerPosition::class, $position->id, [
            'user_id'     => $position->user_id,
            'position'    => $position->position_title,
            'permissions' => $position->permissions,
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('status', "Permissions updated for {$position->user->name}.");
    }
}
