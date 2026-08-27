<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\QrTokenService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Displays the student's live, rotating QR code (see 05-Attendance-Fines.md
 * Part B). There is no stored token and no "Report lost QR" action anymore
 * — the QR image expires and rotates every QrTokenService::WINDOW_SECONDS
 * (~60s) on its own, so a leaked screenshot goes stale on its own.
 */
class QrController extends Controller
{
    /**
     * Initial page load — renders the first live QR image. The page's own
     * JS then polls current() to keep the image fresh without a reload.
     */
    public function show()
    {
        $user = auth()->user();

        abort_unless($user->is_approved, 409, 'Your QR code is not available yet. Please wait for Admin approval.');

        $current = QrTokenService::current($user);
        // Encoded as "<user_id>:<token>" -- the HMAC token alone doesn't
        // identify which student it belongs to (it isn't reversible), so
        // the scan station needs the user_id alongside it. See
        // Officer\AttendanceController::scan.
        $svg = QrCode::size(260)->generate($user->id . ':' . $current['token']);

        return view('student.qr', [
            'qrSvg'     => $svg,
            'expiresIn' => $current['expires_in'],
        ]);
    }

    /**
     * AJAX endpoint the QR page polls to swap in a fresh code before the
     * current one rotates out. Returns pre-rendered SVG markup (server-side,
     * via SimpleSoftwareIO\QrCode) so the client never needs its own
     * QR-generation library.
     */
    public function current()
    {
        $user = auth()->user();

        abort_unless($user->is_approved, 409, 'Your QR code is not available yet. Please wait for Admin approval.');

        $current = QrTokenService::current($user);

        return response()->json([
            'success' => true,
            'data' => [
                'svg'        => (string) QrCode::size(260)->generate($user->id . ':' . $current['token']),
                'expires_in' => $current['expires_in'],
            ],
        ]);
    }
}
