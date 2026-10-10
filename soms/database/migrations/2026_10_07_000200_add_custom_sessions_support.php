<?php
// database/migrations/2026_10_07_000200_add_custom_sessions_support.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an event have any number of named sessions per day (short events,
     * multiple activities) instead of only Morning / Afternoon:
     *  - event_sessions gets `name` and `sort_order`; the (day, session_type)
     *    unique key becomes (day, sort_order)
     *  - session_type and both violation_type columns become plain strings so
     *    'custom' / 'missed_custom_timein' / 'missed_custom_timeout' are allowed
     * Existing rows keep working: morning = 1, afternoon = 2, legacy parade = 3.
     */
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('name', 60)->nullable()->after('session_type');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
            // Keeps the event_day_id foreign key indexed once the old unique key is dropped.
            $table->index('event_day_id', 'event_sessions_event_day_id_index');
        });

        DB::table('event_sessions')->where('session_type', 'morning')->update(['sort_order' => 1]);
        DB::table('event_sessions')->where('session_type', 'afternoon')->update(['sort_order' => 2]);
        DB::table('event_sessions')->where('session_type', 'parade')->update(['sort_order' => 3]);

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropUnique(['event_day_id', 'session_type']);
        });

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('session_type', 20)->change();
            $table->unique(['event_day_id', 'sort_order']);
        });

        Schema::table('event_fine_rules', function (Blueprint $table) {
            $table->string('violation_type', 40)->change();
        });

        Schema::table('fines', function (Blueprint $table) {
            $table->string('violation_type', 40)->change();
        });
    }

    /**
     * Best-effort: removes the new columns/keys. session_type and
     * violation_type stay plain strings (restoring the enums would fail if
     * custom sessions or custom fines already exist).
     */
    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropUnique(['event_day_id', 'sort_order']);
        });

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn(['name', 'sort_order']);
        });
    }
};