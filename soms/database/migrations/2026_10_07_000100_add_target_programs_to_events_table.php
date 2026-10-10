<?php
// database/migrations/2026_10_07_000100_add_target_programs_to_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Programs (users.program values) required to attend this event.
    // NULL / empty = every program is required (the previous behavior).
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('target_programs')->nullable()->after('has_parade');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('target_programs');
        });
    }
};