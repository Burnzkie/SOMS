<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Officer access is no longer derived from position tier
 * (Executive/Administrative/PublicRelations) — see OfficerPermission.
 * Admin now grants permissions per officer via checkboxes, stored here
 * as a JSON array of permission keys (e.g. ["manage_events","view_calendar"]).
 * Defaults to an empty array so newly-appointed officers start with zero
 * access until admin explicitly checks boxes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officer_positions', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('position_title');
        });
    }

    public function down(): void
    {
        Schema::table('officer_positions', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
