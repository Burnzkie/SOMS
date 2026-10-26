<?php
// database/migrations/2026_10_17_000001_add_receipt_number_to_fines_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipt / OR number recorded by the Treasurer when a fine is marked
     * paid. Not unique: one receipt can legitimately cover several of the
     * same student's fines. The Auditor's report flags a receipt reused
     * across DIFFERENT students instead.
     */
    public function up(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->string('receipt_number', 60)->nullable()->after('cleared_at');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->dropIndex(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
