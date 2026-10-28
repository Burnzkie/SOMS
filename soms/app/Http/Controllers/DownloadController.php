<?php
// app/Http/Controllers/DownloadController.php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, unauthenticated download page for the SOMS Android APK. Always
 * shows whatever the latest published AppVersion currently is — the same
 * row /admin/app-versions publishes to and the app itself checks via
 * GET /api/v1/app-version — so this page never goes stale after a new
 * release; nobody needs to re-share a link.
 *
 * The APK itself is streamed through apk() so users never leave our own
 * domain (some in-app browsers can't resolve *.r2.dev).
 *
 * See 10-Mobile-Deployment.md Part B — in-app auto-update.
 */
class DownloadController extends Controller
{
    /**
     * Renders the public download page with the latest Android version
     * and a QR code that points back to this page.
     */
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

    /**
     * Streams the APK from R2 through our own domain. Some in-app browsers
     * (e.g. the one a phone camera opens) can't resolve *.r2.dev, so the
     * download link must stay on the same host as the page.
     */
    public function apk(AppVersion $appVersion): StreamedResponse
    {
        // Only proxy our own bucket, never an arbitrary stored URL.
        $apkHost = parse_url($appVersion->apk_url, PHP_URL_HOST);
        abort_unless($apkHost && $apkHost === config('services.apk.host'), 404);

        $upstream = Http::withOptions(['stream' => true])
            ->timeout(120)
            ->get($appVersion->apk_url);
        abort_unless($upstream->successful(), 502, 'APK temporarily unavailable.');

        $body = $upstream->toPsrResponse()->getBody();

        // Strip anything unexpected from the version string before using it in a filename.
        $fileName = 'SOMS-' . preg_replace('/[^A-Za-z0-9.\-]/', '', $appVersion->version_name) . '.apk';

        $headers = [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // Lets the phone show real download progress.
        if ($length = $upstream->header('Content-Length')) {
            $headers['Content-Length'] = $length;
        }

        return response()->stream(function () use ($body) {
            // Read in 8 KB chunks so a large APK never sits in memory.
            while (! $body->eof()) {
                echo $body->read(8192);
                flush();
            }
        }, 200, $headers);
    }
}