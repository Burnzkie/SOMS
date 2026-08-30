<?php

// app/Http/Controllers/ProfileController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Self-only personal info editing for the web dashboard — no target user
 * param, always acts on the authenticated user. Mirrors
 * Api\ProfileController's field set exactly (name, email, department,
 * program, level) so mobile and web stay in sync; deliberately excludes
 * student_id, role, is_approved, password (see change-password routes),
 * and avatar (see AvatarController) for the same reasons noted there.
 */
class ProfileController extends Controller
{
    /**
     * GET /settings/profile
     */
    public function edit(Request $request)
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    /**
     * PUT /settings/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'program'    => ['nullable', 'string', 'max:255'],
            'level'      => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated)->save();

        ActivityLog::record($user->id, 'profile_updated', null, null, [
            'fields' => array_keys($validated),
        ], $request->ip());

        return redirect()->route('settings.profile.edit')
            ->with('status', 'Profile updated successfully.');
    }
}
