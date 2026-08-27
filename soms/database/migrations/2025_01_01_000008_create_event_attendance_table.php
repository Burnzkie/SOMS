<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('scan_type', ['time_in', 'time_out']);
            $table->dateTime('scanned_at');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'flagged_for_review']);
            $table->boolean('is_manual_override')->default(false);
            $table->string('override_reason')->nullable();
            $table->dateTime('device_scanned_at')->nullable();
            $table->boolean('synced_from_offline_queue')->default(false);
            $table->timestamps();

            $table->unique(['event_session_id', 'user_id', 'scan_type'], 'event_attendance_session_user_scan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance');
    }
};
