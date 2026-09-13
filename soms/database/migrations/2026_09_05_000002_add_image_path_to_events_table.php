<?php
// database/migrations/2026_09_05_000002_add_image_path_to_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets officers attach a cover image to an event (shown on the officer
 * dashboard's "Upcoming Event" panel and the events list). Stored on the
 * same R2 disk / SafeImageUpload pipeline as user avatars — see
 * AvatarController for the re-encode/validate pattern this mirrors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
