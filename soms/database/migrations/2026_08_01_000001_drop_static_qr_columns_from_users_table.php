<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR codes are now rotating/time-windowed and computed live server-side
 * (App\Services\QrTokenService) instead of being stored per-account.
 * qr_token / qr_version / qr_revoked existed solely to support the old
 * static-token + manual-revoke model and are no longer read or written
 * anywhere in the codebase. qr_generated_at is kept — it now just records
 * when the student's QR access was activated (on Admin approval).
 *
 * See 05-Attendance-Fines.md Part B and CHANGELOG.md for the full
 * rationale (proxy-attendance risk of a long-lived, shareable QR image).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // qr_token was created with ->unique() (see the SOMS-columns
            // migration). MySQL cascades an index drop when you drop the
            // column, but SQLite's native DROP COLUMN refuses to drop a
            // column that's part of a UNIQUE index -- this is what broke
            // `migrate:fresh` under phpunit.xml's sqlite :memory: test DB.
            // Drop the index explicitly first so this works on both drivers.
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['qr_token', 'qr_version', 'qr_revoked']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_token')->unique()->nullable()->after('must_change_password');
            $table->unsignedInteger('qr_version')->default(1)->after('qr_token');
            $table->boolean('qr_revoked')->default(false)->after('qr_version');
        });
    }
};
