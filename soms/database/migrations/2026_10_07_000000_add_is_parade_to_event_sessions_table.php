<?php
// database/migrations/2026_10_07_000000_add_is_parade_to_event_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Marks a Morning session as the parade check-in for that date.
    // Legacy 'parade' session rows are untouched and keep working.
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->boolean('is_parade')->default(false)->after('session_type');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn('is_parade');
        });
    }
};