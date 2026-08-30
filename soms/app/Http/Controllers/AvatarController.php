<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\SafeImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Avatar as a singleton CRUD resource — one photo per user, self-only,
 * available to every authenticated role (admin, officer, student).
 *
 * Registered via Route::singleton('avatar', AvatarController::class) in
 * routes/web.php, giving proper REST verbs/names for a per-user resource
 * that has no ID of its own:
 *   GET    /avatar   -> show()    (avatar.show)
 *   POST   /avatar   -> store()   (avatar.store)   — create, none exists yet
 *   PUT    /avatar   -> update()  (avatar.update)  — replace an existing one
 *   DELETE /avatar   -> destroy() (avatar.destroy)
 *
 * store() and update() are deliberately NOT interchangeable: store() 409s
 * if a photo already exists (use update() instead), update() 404s if none
 * exists yet (use store() instead). This is stricter than most singleton
 * avatar implementations (which usually let one action do both), chosen
 * here so each HTTP verb has one unambiguous meaning.
 */
class AvatarController extends Controller
{
    /**
     * GET /avatar
     *
     * Read-only lookup of the current user's avatar. Mainly useful for
     * AJAX/JS callers that want to check state without re-rendering the
     * whole page (the sidebar itself renders avatar_path directly via
     * Blade, so this isn't needed there).
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
     * POST /avatar — create. Fails if the user already has a photo (use
     * update() to replace it instead) — see class docblock.
     *
     * Security layers, in order:
     * 1. Route middleware (routes/web.php): auth + must.change.password —
     *    any logged-in user with a usable account reaches this action.
     * 2. Policy (UserPolicy::createAvatar): a user can only create their
     *    OWN avatar — blocks acting on someone else's.
     * 3. Form-level validation: rejects non-image/oversized uploads by
     *    extension+MIME before the file ever touches disk. Fast fail.
     * 4. SafeImageUpload::store(): re-validates the file's REAL content via
     *    getimagesize() (not just extension/MIME, which can be spoofed),
     *    enforces a hard 2MB cap, restricts to JPEG/PNG/WEBP, and re-encodes
     *    the image from scratch into a fresh JPEG. Re-encoding strips EXIF
     *    metadata and any payload smuggled inside image bytes, and the
     *    output filename is a server-generated UUID, never the client's
     *    filename — no path traversal, no way to guess/overwrite another
     *    file.
     * 5. Change is logged to activity_logs for audit trail.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorize('createAvatar', $user);

        abort_if($user->avatar_path, 409, 'You already have a photo — use update instead.');

        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $newPath = SafeImageUpload::store($request->file('avatar'), 'r2', 'avatars');

        $user->forceFill(['avatar_path' => $newPath])->save();

        ActivityLog::record($user->id, 'avatar_created', User::class, $user->id);

        return back()->with('status', 'Photo added.');
    }

    /**
     * PUT/PATCH /avatar — replace an existing photo. Fails if the user has
     * no photo yet (use store() to create one first) — see class docblock.
     *
     * Same validation/re-encoding pipeline as store(); additionally
     * deletes the old file once the new one is confirmed saved, so
     * replaced avatars don't pile up as orphaned files on disk.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $this->authorize('updateAvatar', $user);

        abort_unless($user->avatar_path, 404, "You don't have a photo yet — upload one first.");

        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $oldPath = $user->avatar_path;

        $newPath = SafeImageUpload::store($request->file('avatar'), 'r2', 'avatars');

        $user->forceFill(['avatar_path' => $newPath])->save();

        Storage::disk('r2')->delete($oldPath);

        ActivityLog::record($user->id, 'avatar_updated', User::class, $user->id);

        return back()->with('status', 'Photo updated.');
    }

    /**
     * DELETE /avatar
     *
     * Removes the user's own profile photo — clears avatar_path and
     * deletes the stored file. No-op with a friendly message if there's
     * nothing to remove.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $this->authorize('deleteAvatar', $user);

        if (!$user->avatar_path) {
            return back()->with('status', "You don't have a photo to remove.");
        }

        Storage::disk('r2')->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        ActivityLog::record($user->id, 'avatar_removed', User::class, $user->id);

        return back()->with('status', 'Photo removed.');
    }
}
