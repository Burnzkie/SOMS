<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands events.type from ['foundation_day','other'] to the full preset
 * list in App\Support\EventTypes — feeds the calendar quick-create
 * dropdown (web dateClick modal + mobile quick-create sheet). Raw SQL
 * because Laravel's Schema::table()->change() needs doctrine/dbal for
 * enum columns, which this project doesn't otherwise depend on.
 *
 * MySQL-only in production/Clever Cloud, but this ran unguarded on every
 * `php artisan test` too (phpunit.xml points DB_CONNECTION at sqlite
 * :memory:) — SQLite has neither `ALTER TABLE ... MODIFY` nor a native
 * ENUM type, so this failed with a syntax error on every single test in
 * the suite, every time, via RefreshDatabase. Guarded below so it only
 * runs the MySQL DDL on MySQL.
 *
 * On SQLite the original create_events_table migration's
 * `$table->enum('type', ['foundation_day', 'other'])` already compiles
 * to a CHECK constraint permitting exactly those two values, and every
 * test in this codebase only ever creates events with type 'other' or
 * 'foundation_day' — so leaving that constraint as-is is a safe no-op
 * for testing purposes. If a future test needs one of the newer preset
 * types (e.g. 'week_of_prayer') against SQLite, that CHECK constraint
 * will need a full table rebuild (rename -> recreate -> copy -> drop),
 * since SQLite can't ALTER a CHECK constraint in place.
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
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $list = implode(',', array_map(fn ($v) => "'$v'", self::NEW_ENUM));
        DB::statement("ALTER TABLE events MODIFY type ENUM($list) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Any event created with a new type is reset to 'other' first, or the
        // narrower enum below would reject the row and the rollback would fail.
        DB::table('events')->whereNotIn('type', self::OLD_ENUM)->update(['type' => 'other']);

        $list = implode(',', array_map(fn ($v) => "'$v'", self::OLD_ENUM));
        DB::statement("ALTER TABLE events MODIFY type ENUM($list) NOT NULL DEFAULT 'other'");
    }
};
