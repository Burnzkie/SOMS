<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom Entries (the grey non-attendance calendar markers) removed
 * entirely — SOMS Events are now the only thing on the officer calendar.
 * See Officer\CalendarController and officer/calendar/index.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('calendar_entries');
    }

    public function down(): void
    {
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
