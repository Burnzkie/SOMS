<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_fine_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->enum('violation_type', [
                'missed_morning_timein',
                'missed_morning_timeout',
                'missed_afternoon_timein',
                'missed_afternoon_timeout',
                'missed_parade',
            ]);
            $table->decimal('amount', 8, 2);

            $table->unique(['event_id', 'violation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_fine_rules');
    }
};
