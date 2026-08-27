<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_session_id')->constrained()->cascadeOnDelete();
            $table->enum('violation_type', [
                'missed_morning_timein',
                'missed_morning_timeout',
                'missed_afternoon_timein',
                'missed_afternoon_timeout',
                'missed_parade',
            ]);
            $table->string('reason')->nullable();
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['unpaid', 'paid', 'waived'])->default('unpaid');
            $table->dateTime('issued_at');
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_session_id', 'violation_type'], 'fines_user_session_violation_unique');
            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
