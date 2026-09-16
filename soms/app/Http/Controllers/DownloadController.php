<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Public, unauthenticated download page for the SOMS Android APK. Always
 * shows whatever the latest published AppVersion currently is — the same
 * row /admin/app-versions publishes to and the app itself checks via
 * GET /api/v1/app-version — so this page never goes stale after a new
 * release; nobody needs to re-share a link.
 *
 * See 10-Mobile-Deployment.md Part B — in-app auto-update.
 */
class DownloadController extends Controller
{
    public function show()
    {
        $version = AppVersion::latestFor('android');

        // Same QR generation approach already used for attendance codes
        // (simplesoftwareio/simple-qrcode) — svg output needs no extra
        // PHP extension (imagick/gd) beyond what's already required.
        $qrSvg = $version
            ? QrCode::size(220)->generate(url('/download'))
            : null;

        return view('download', [
            'version' => $version,
            'qrSvg' => $qrSvg,
        ]);
    }
}
