<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expands events.type from ['foundation_day','other'] to the full preset
 * list in App\Support\EventTypes — feeds the calendar quick-create
 * dropdown (web dateClick modal + mobile quick-create sheet). Raw SQL
 * because Laravel's Schema::table()->change() needs doctrine/dbal for
 * enum columns, which this project doesn't otherwise depend on.
 */
return new class extends Migration
{
    private const OLD_ENUM = ['foundation_day', 'other'];

    private const NEW_ENUM = [
        'student_assembly',
        'friendship_week',
        'community_service',
        'academic_activities',
        'talent_festival',
        'freshmen_welcome',
        'week_of_prayer',
        'intramurals',
        'technology_week',
        'acquaintance_party',
        'foundation_day',
        'leadership_recognition',
        'christmas_celebration',
        'other',
    ];

    public function up(): void
    {
        $list = implode(',', array_map(fn ($v) => "'$v'", self::NEW_ENUM));
        DB::statement("ALTER TABLE events MODIFY type ENUM($list) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        // Any event created with a new type is reset to 'other' first, or the
        // narrower enum below would reject the row and the rollback would fail.
        DB::table('events')->whereNotIn('type', self::OLD_ENUM)->update(['type' => 'other']);

        $list = implode(',', array_map(fn ($v) => "'$v'", self::OLD_ENUM));
        DB::statement("ALTER TABLE events MODIFY type ENUM($list) NOT NULL DEFAULT 'other'");
    }
};
