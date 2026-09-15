<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppVersion;
use Illuminate\Http\Request;

/**
 * See 10-Mobile-Deployment.md Part B — in-app auto-update.
 *
 * Publishing a version here is a metadata-only step: upload the signed
 * release APK to R2 yourself first (same bucket/flow as everything else
 * on FILESYSTEM_DISK=r2), then paste the resulting public URL into this
 * form. This controller never touches the APK file itself — R2 upload
 * is a manual step until/unless that's worth automating.
 */
class AppVersionController extends Controller
{
    public function index()
    {
        $versions = AppVersion::with('publisher')
            ->orderByDesc('version_code')
            ->get();

        return view('admin.app-versions', [
            'versions' => $versions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'platform'                    => 'required|in:android',
            'version_code'                => 'required|integer|min:1|unique:app_versions,version_code,NULL,id,platform,' . $request->input('platform'),
            'version_name'                => 'required|string|max:20',
            'apk_url'                     => 'required|url|max:2048',
            'changelog'                   => 'nullable|string|max:2000',
            'force_update'                => 'sometimes|boolean',
            'min_supported_version_code'  => 'nullable|integer|min:1',
        ]);

        $version = AppVersion::create([
            ...$validated,
            'force_update' => $request->boolean('force_update'),
            'is_active'    => true,
            'published_by' => auth()->id(),
        ]);

        ActivityLog::record(auth()->id(), 'app_version_published', AppVersion::class, $version->id, [
            'version_code' => $version->version_code,
            'version_name' => $version->version_name,
            'force_update' => $version->force_update,
        ]);

        return redirect()->route('admin.app-versions.index')
            ->with('status', "Version {$version->version_name} ({$version->version_code}) published.");
    }

    /**
     * Kill switch for a bad release — flips is_active off rather than
     * deleting, so the row (and its activity log entry) stays for audit.
     * The app then falls back to the next-highest active version_code.
     */
    public function deactivate(AppVersion $appVersion)
    {
        $appVersion->update(['is_active' => false]);

        ActivityLog::record(auth()->id(), 'app_version_deactivated', AppVersion::class, $appVersion->id, [
            'version_code' => $appVersion->version_code,
            'version_name' => $appVersion->version_name,
        ]);

        return redirect()->route('admin.app-versions.index')
            ->with('status', "Version {$appVersion->version_name} deactivated.");
    }
}
