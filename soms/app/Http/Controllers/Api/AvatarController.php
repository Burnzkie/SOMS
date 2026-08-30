<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\SafeImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile counterpart of App\Http\Controllers\AvatarController — same
 * singleton CRUD split (show/store/update/destroy), same SafeImageUpload
 * pipeline and UserPolicy self-only rule, JSON responses instead of
 * redirects. Available to every authenticated role, self-only.
 */
class AvatarController extends Controller
{
    /**
     * GET /api/v1/avatar
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewAvatar', $user);

        return response()->json([
            'success' => true,
            'data' => [
                'has_avatar' => (bool) $user->avatar_path,
                'avatar_url' => $user->avatar_path ? Storage::disk('r2')->url($user->avatar_path) : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/avatar — create. 409s if a photo already exists (use
     * PUT /api/v1/avatar to replace it instead).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorize('createAvatar', $user);

        if ($user->avatar_path) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a photo — use update instead.',
            ], 409);
        }

        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $newPath = SafeImageUpload::store($request->file('avatar'), 'r2', 'avatars');

        $user->forceFill(['avatar_path' => $newPath])->save();

        ActivityLog::record($user->id, 'avatar_created', User::class, $user->id, ['platform' => 'mobile'], $request->ip());

        return response()->json([
            'success' => true,
            'data' => ['avatar_url' => Storage::disk('r2')->url($newPath)],
            'message' => 'Photo added.',
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/avatar — replace an existing photo. 404s if none
     * exists yet (use POST /api/v1/avatar to create one first).
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('updateAvatar', $user);

        if (!$user->avatar_path) {
            return response()->json([
                'success' => false,
                'message' => "You don't have a photo yet — upload one first.",
            ], 404);
        }

        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $oldPath = $user->avatar_path;

        $newPath = SafeImageUpload::store($request->file('avatar'), 'r2', 'avatars');

        $user->forceFill(['avatar_path' => $newPath])->save();

        Storage::disk('r2')->delete($oldPath);

        ActivityLog::record($user->id, 'avatar_updated', User::class, $user->id, ['platform' => 'mobile'], $request->ip());

        return response()->json([
            'success' => true,
            'data' => ['avatar_url' => Storage::disk('r2')->url($newPath)],
            'message' => 'Photo updated.',
        ]);
    }

    /**
     * DELETE /api/v1/avatar
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $this->authorize('deleteAvatar', $user);

        if (!$user->avatar_path) {
            return response()->json(['success' => true, 'data' => null, 'message' => "You don't have a photo to remove."]);
        }

        Storage::disk('r2')->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        ActivityLog::record($user->id, 'avatar_removed', User::class, $user->id, ['platform' => 'mobile'], $request->ip());

        return response()->json(['success' => true, 'data' => null, 'message' => 'Photo removed.']);
    }
}
