<?php
// database/migrations/2026_09_06_000001_add_color_to_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets officers pick a custom calendar color per event, instead of every
 * event rendering with the same hardcoded blue/orange — see
 * Officer\CalendarController::buildEventFeed, which used to hardcode
 * '#5B5BF6' for every single event on the calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
