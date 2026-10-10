<?php
// app/Http/Controllers/Api/Auth/SessionController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Sanctum token ("session") management for the mobile app.
 *
 * SANCTUM_TOKEN_EXPIRATION_MINUTES (config/sanctum.php) defaults to 30
 * days and there was previously no way for a user to see or kill a
 * token before it naturally expired -- e.g. a lost/stolen phone stayed
 * logged in for up to 30 days with no recourse except an admin manually
 * deleting rows. This gives the user that control themselves, mirroring
 * "log out other devices" in most consumer apps.
 *
 * Deliberately separate from LoginController -- that file owns
 * issuing a token (login) and killing the *current* one (logout);
 * this one is about managing the set of tokens a user already holds.
 */
class SessionController extends Controller
{
    /**
     * GET /api/v1/auth/sessions
     * Lists every active token for the authenticated user so the mobile
     * "Security" screen can show something like "iPhone -- last active
     * 2 hours ago" per row, with the current session flagged so it can't
     * be revoked by mistake from its own list entry.
     */
    public function index(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id'            => $token->id,
                'name'          => $token->name,
                'last_used_at'  => $token->last_used_at,
                'created_at'    => $token->created_at,
                'is_current'    => $token->id === $currentTokenId,
            ]);

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    /**
     * DELETE /api/v1/auth/sessions/{tokenId}
     * Revoke one specific token. Scoped to the authenticated user's own
     * tokens() relation -- there is no route-model-binding on
     * PersonalAccessToken here on purpose, so a user can never even
     * probe whether a token ID belonging to someone else exists.
     */
    public function destroy(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found.',
            ], 404);
        }

        $wasCurrent = $token->id === $request->user()->currentAccessToken()?->id;
        $token->delete();

        ActivityLog::record($request->user()->id, 'session_revoked', null, null, [
            'token_id'   => $tokenId,
            'was_current'=> $wasCurrent,
            'platform'   => 'mobile',
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => $wasCurrent ? 'You have been logged out.' : 'Session revoked.',
            'data' => ['was_current' => $wasCurrent],
        ]);
    }

    /**
     * POST /api/v1/auth/sessions/revoke-others
     * "Log out everywhere else" -- keeps the token making this very
     * request alive, deletes every other token belonging to the user.
     * The natural action to take right after suspecting a lost/stolen
     * device without also locking yourself out of the device you're
     * using to respond.
     */
    public function destroyOthers(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $revokedCount = $request->user()->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        ActivityLog::record($request->user()->id, 'sessions_revoked_all_others', null, null, [
            'revoked_count' => $revokedCount,
            'platform'      => 'mobile',
        ], $request->ip());

        return response()->json([
            'success' => true,
            'message' => $revokedCount === 1
                ? '1 other session revoked.'
                : "{$revokedCount} other sessions revoked.",
            'data' => ['revoked_count' => $revokedCount],
        ]);
    }
}
