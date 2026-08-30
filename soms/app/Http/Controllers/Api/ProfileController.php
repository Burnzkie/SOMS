<?php

// app/Http/Controllers/Api/ProfileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Self-only personal info editing — no target user param, always acts on
 * the authenticated user. Mirrors ChangePasswordController's pattern:
 * operates directly on $request->user(), no policy needed since there's
 * no "other user" case to guard against.
 *
 * Deliberately does NOT allow editing: student_id, role, is_approved,
 * password (use /auth/change-password), avatar (use /avatar) — these stay
 * out of $fillable-driven mass update for the same reasons noted in
 * User::$fillable's docblock.
 */
class ProfileController extends Controller
{
    /**
     * GET /api/v1/profile
     */
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->only([
                'id', 'student_id', 'name', 'email',
                'department', 'program', 'level', 'role',
            ]),
        ]);
    }

    /**
     * PUT /api/v1/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'program'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'level'      => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated)->save();

        ActivityLog::record($user->id, 'profile_updated', null, null, [
            'fields' => array_keys($validated),
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user->only([
                'id', 'student_id', 'name', 'email',
                'department', 'program', 'level', 'role',
            ]),
        ]);
    }
}