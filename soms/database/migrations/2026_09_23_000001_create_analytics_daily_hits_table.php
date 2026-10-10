<?php
// database/migrations/2026_09_23_000001_create_analytics_daily_hits_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party, privacy-by-design usage analytics (Sep 2026).
 *
 * Deliberately stores ONLY: a date, a route name, a platform, and a
 * count. No user_id, no IP address, no user agent, no session/cookie
 * of any kind. This is not "anonymized" data (hashed IPs, truncated
 * identifiers) — it's aggregate-only from the moment it's written, so
 * there is no personal data here to protect, retain a policy for, or
 * accidentally leak. Given SOMS handles student records under RA
 * 10173 (Data Privacy Act of 2012, cited in the thesis references),
 * this was chosen over any third-party analytics service specifically
 * so student usage data never leaves the school's own database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_hits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('route_name'); // e.g. "admin.dashboard", "api.officer.events.index"
            $table->enum('platform', ['web', 'mobile']);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['date', 'route_name', 'platform']);
            $table->index('date'); // for the "last N days" summary queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_hits');
    }
};
