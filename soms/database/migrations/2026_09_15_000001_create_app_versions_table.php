<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * See 10-Mobile-Deployment.md Part B — in-app auto-update.
 *
 * One row per published Android release. The mobile app calls
 * GET /api/v1/app-version on launch and compares `version_code`
 * against its own PackageInfo.buildNumber (via package_info_plus).
 *
 * `platform` is included even though only 'android' is used today
 * (APK distribution, no iOS build) so this table doesn't need a
 * shape change if iOS TestFlight distribution is ever added later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('android');

            // Matches Android's versionCode (flutter.versionCode /
            // pubspec.yaml's `+N` build number) — integer, monotonically
            // increasing, what actually decides "is this newer".
            $table->unsignedInteger('version_code');

            // Human-readable, matches Android's versionName (pubspec.yaml's
            // `X.Y.Z` before the `+`) — display-only, never compared.
            $table->string('version_name');

            $table->string('apk_url');
            $table->text('changelog')->nullable();

            // If true, the app should block usage (not just nag) until the
            // user updates. Use sparingly — e.g. a breaking API change.
            $table->boolean('force_update')->default(false);

            // Below this version_code, the app refuses to run at all
            // regardless of force_update on the *latest* row — covers the
            // case where an old install skipped several releases and the
            // API has since dropped backward compatibility for it.
            $table->unsignedInteger('min_supported_version_code')->nullable();

            // Manual kill switch for a bad release — flip to false instead
            // of deleting the row, so the audit trail (who published what,
            // when) stays intact.
            $table->boolean('is_active')->default(true);

            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['platform', 'is_active', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
