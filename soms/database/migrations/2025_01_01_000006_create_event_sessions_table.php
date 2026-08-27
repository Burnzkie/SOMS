<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->enum('session_type', ['morning', 'afternoon', 'parade']);
            $table->dateTime('timein_start');
            $table->dateTime('timein_end');
            $table->dateTime('timeout_start')->nullable();
            $table->dateTime('timeout_end')->nullable();
            $table->boolean('fines_issued')->default(false);
            $table->timestamps();

            $table->unique(['event_day_id', 'session_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
