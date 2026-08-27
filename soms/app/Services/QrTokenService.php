<?php

namespace App\Services;

use App\Models\User;

/**
 * Personal student QR token — rotating, time-windowed, HMAC-signed.
 * See 05-Attendance-Fines.md Part B.
 *
 * Design (replaces the old static-per-account revoke/reissue model):
 *   payload = user.id . '|' . floor(unix_time / WINDOW_SECONDS)
 *   token   = hash_hmac('sha256', payload, app.key)
 *
 * The token is never stored — it's computed live, server-side only, on
 * every request (both when issuing it to the student's device and when
 * verifying a scan). Because it naturally expires within ~1 minute, there
 * is no separate "revoke" concept: a leaked screenshot goes stale before
 * it can realistically be reused. This intentionally replaces the old
 * qr_token / qr_version / qr_revoked / "Report lost QR" mechanism.
 *
 * app.key never leaves the server, so the token cannot be computed
 * client-side — the student's app/browser must poll a live endpoint
 * (QrController::current / StudentApiController::qrCurrent) rather than
 * generate the QR offline. See 10-Mobile-Deployment.md for the
 * connectivity tradeoff this introduces.
 */
class QrTokenService
{
    /** How often the visible QR changes, in seconds. */
    public const WINDOW_SECONDS = 60;

    /**
     * Verification drift tolerance, in seconds, applied symmetrically
     * (past and future) around the scan's reference time. Chosen loose
     * (vs. a tight ±60s) to absorb network latency, HID scanner lag, and
     * the offline scan-batch path's device-clock drift — see
     * 03-Auth-Security.md §20.10.
     */
    public const DRIFT_TOLERANCE_SECONDS = 180;

    public static function currentWindow(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::WINDOW_SECONDS);
    }

    protected static function generateForWindow(User $user, int $window): string
    {
        $payload = $user->id . '|' . $window;

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * The token currently valid for this instant, plus how many seconds
     * remain before it rotates — used by the live-refresh QR display.
     */
    public static function current(User $user): array
    {
        $now = time();
        $window = self::currentWindow($now);

        return [
            'token'       => self::generateForWindow($user, $window),
            'expires_in'  => self::WINDOW_SECONDS - ($now % self::WINDOW_SECONDS),
            'server_time' => $now,
        ];
    }

    /**
     * Verify a scanned token against a drift-tolerant band of time windows.
     *
     * $referenceTime defaults to server-receipt time (live scan path, per
     * Architecture Decision 2.11). The offline scan-batch path passes the
     * device-reported `device_scanned_at` instead — see
     * 05-Attendance-Fines.md Part C and Architecture Decision 2.27.
     */
    public static function verify(User $user, string $token, ?int $referenceTime = null): bool
    {
        $referenceTime ??= time();
        $steps = intdiv(self::DRIFT_TOLERANCE_SECONDS, self::WINDOW_SECONDS);
        $centerWindow = self::currentWindow($referenceTime);

        for ($offset = -$steps; $offset <= $steps; $offset++) {
            if (hash_equals(self::generateForWindow($user, $centerWindow + $offset), $token)) {
                return true;
            }
        }

        return false;
    }
}
