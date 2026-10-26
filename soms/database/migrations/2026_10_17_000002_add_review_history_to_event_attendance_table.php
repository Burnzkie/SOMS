<?php
// database/migrations/2026_10_17_000002_add_review_history_to_event_attendance_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keeps the resolution history of a flagged scan. Reviewing a scan
     * overwrites its status (present / absent), so without these columns the
     * fact that it was ever flagged, and who decided, would live only in the
     * activity log.
     */
    public function up(): void
    {
        Schema::table('event_attendance', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('synced_from_offline_queue')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_decision', 10)->nullable()->after('reviewed_at'); // approved | rejected
        });
    }

    public function down(): void
    {
        Schema::table('event_attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_decision']);
        });
    }
};
