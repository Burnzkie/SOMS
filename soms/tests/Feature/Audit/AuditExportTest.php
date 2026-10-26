<?php
// tests/Feature/Audit/AuditExportTest.php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/** CSV exports: correct content, neutralised formulas, logged, permission-gated. */
class AuditExportTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    private function csv(string $route, array $query = []): string
    {
        $response = $this->actingAs($this->makeAuditor())->get(route($route, $query));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        return $response->streamedContent();
    }

    public function test_the_fines_export_lists_each_fine_with_status_and_receipt(): void
    {
        $this->makeFine($this->makeStudent('Ana Cruz', ['student_id' => 'S0000000042']), [
            'status' => 'paid', 'cleared_by' => $this->admin->id, 'cleared_at' => now(), 'receipt_number' => 'OR-2026-0042',
        ]);

        $csv = $this->csv('officer.audit.export.fines');

        // Headers with a space are quoted by fputcsv.
        $this->assertStringContainsString('"Fine ID","Student ID",Student,Event', str_replace("\xEF\xBB\xBF", '', $csv));
        $this->assertStringContainsString('S0000000042', $csv);
        $this->assertStringContainsString('Ana Cruz', $csv);
        $this->assertStringContainsString('OR-2026-0042', $csv);
        $this->assertStringContainsString('paid', $csv);
    }

    public function test_the_export_follows_the_page_filters(): void
    {
        $this->makeFine($this->makeStudent('Unpaid Una'));
        $this->makeFine($this->makeStudent('Paid Pia'), ['status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1']);

        $csv = $this->csv('officer.audit.export.fines', ['status' => 'paid']);

        $this->assertStringContainsString('Paid Pia', $csv);
        $this->assertStringNotContainsString('Unpaid Una', $csv);
    }

    public function test_spreadsheet_formulas_in_names_are_neutralised(): void
    {
        $this->makeFine($this->makeStudent('=HYPERLINK("http://evil.example","click")'));
        $this->makeFine($this->makeStudent('+cmd|calc'), ['violation_type' => 'missed_morning_timeout', 'amount' => 30]);

        $csv = $this->csv('officer.audit.export.fines');

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd|calc", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|,)"?=HYPERLINK/m', $csv);
    }

    public function test_the_fines_export_does_not_query_per_fine(): void
    {
        foreach (range(1, 12) as $n) {
            $this->makeFine($this->makeStudent("Student {$n}"), [
                'status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => "OR-{$n}",
            ]);
        }

        $auditor = $this->makeAuditor();
        DB::enableQueryLog();
        $this->actingAs($auditor)->get(route('officer.audit.export.fines'))->assertOk()->streamedContent();
        $userLookups = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "users"'))->count();
        DB::disableQueryLog();

        // The signed-in officer, plus the students and settlers loaded in bulk: never one per fine.
        $this->assertLessThan(8, $userLookups);
    }

    public function test_the_discrepancy_export_lists_the_findings_of_the_checks(): void
    {
        $this->makeFine($this->makeStudent('Ana'), ['amount' => 45]);

        $csv = $this->csv('officer.audit.export.discrepancies');

        $this->assertStringContainsString('Fine amount differs from the fine rule', $csv);
        $this->assertStringContainsString('medium', $csv);
    }

    public function test_every_export_is_recorded_in_the_activity_log(): void
    {
        $auditor = $this->makeAuditor();

        $this->actingAs($auditor)->get(route('officer.audit.export.fines'))->assertOk()->streamedContent();
        $this->actingAs($auditor)->get(route('officer.audit.export.discrepancies'))->assertOk()->streamedContent();

        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_export', 'user_id' => $auditor->id]);
    }

    public function test_exporting_needs_the_reports_permission(): void
    {
        $viewer = $this->makeOfficerWith('Auditor', ['view_fines']); // may view fines, may not export

        $this->actingAs($viewer)->get(route('officer.audit.fines'))->assertOk();
        $this->actingAs($viewer)->get(route('officer.audit.export.fines'))->assertForbidden();
        $this->actingAs($viewer)->get(route('officer.audit.export.discrepancies'))->assertForbidden();
    }
}
