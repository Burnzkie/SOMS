<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

/**
 * GET /api/v1/app-version
 * See 10-Mobile-Deployment.md Part B — in-app auto-update.
 *
 * Deliberately public (no auth:sanctum) — the app needs to be able to
 * check this before login too (e.g. force_update should block a stale
 * install even at the login screen, not just post-auth). Sits inside
 * the outer throttle:api group in routes/api.php like the other public
 * endpoints (academic-programs), so it's still rate-limited.
 */
class AppVersionController extends Controller
{
    public function show(Request $request)
    {
        $platform = $request->query('platform', 'android');

        $latest = AppVersion::latestFor($platform);

        if (!$latest) {
            // No version has ever been published for this platform — the
            // app should treat this as "no update available", not as an
            // error. Distinct from a 404/500 so Flutter doesn't need a
            // special case: `data` is null, `updateAvailable` is false.
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'versionCode'            => $latest->version_code,
                'versionName'            => $latest->version_name,
                'apkUrl'                 => $latest->apk_url,
                'changelog'              => $latest->changelog,
                'forceUpdate'            => $latest->force_update,
                'minSupportedVersionCode' => $latest->min_supported_version_code,
            ],
        ]);
    }
}
