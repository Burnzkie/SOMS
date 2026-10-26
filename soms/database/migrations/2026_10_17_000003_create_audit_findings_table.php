<?php
// database/migrations/2026_10_17_000003_create_audit_findings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit findings: what the Auditor reports and how it was resolved.
     * The Auditor records and tracks them; the Admin acknowledges and
     * resolves them. Nothing here changes the record the finding is about.
     */
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // What the finding is about: fine | attendance | session | general.
            $table->string('reference_type', 20)->default('general');
            $table->unsignedBigInteger('reference_id')->nullable();
            // Set when raised from the discrepancy report; stops the same issue
            // being reported twice while an earlier finding is still open.
            $table->string('check_type', 40)->nullable();

            $table->string('title', 160);
            $table->text('issue');
            $table->text('evidence')->nullable();
            $table->string('severity', 10)->default('medium');   // low | medium | high
            $table->string('status', 15)->default('open');       // open | acknowledged | resolved | dismissed

            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reported_at');

            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['check_type', 'reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};
