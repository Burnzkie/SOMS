<?php
// tests/Feature/Audit/AuditFindingTest.php

namespace Tests\Feature\Audit;

use App\Models\AuditFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Findings: the Auditor records and tracks them, the Admin acknowledges and
 * resolves them, and nobody edits the record a finding is about.
 */
class AuditFindingTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    private function newFinding(array $overrides = []): AuditFinding
    {
        return AuditFinding::create($overrides + [
            'organization_id' => $this->org->id,
            'reference_type' => 'fine',
            'reference_id' => 1,
            'title' => 'Duplicate receipt',
            'issue' => 'Same receipt used twice.',
            'severity' => 'high',
            'status' => 'open',
            'reported_by' => $this->makeAuditor()->id,
            'reported_at' => now(),
        ]);
    }

    public function test_the_auditor_records_a_finding_and_it_is_logged(): void
    {
        $auditor = $this->makeAuditor();

        $this->actingAs($auditor)->post(route('officer.audit.findings.store'), [
            'reference_type' => 'fine', 'reference_id' => 12,
            'title' => 'Waiver without approval', 'issue' => 'No approver recorded.', 'severity' => 'medium',
        ])->assertRedirect();

        $finding = AuditFinding::firstOrFail();
        $this->assertSame('open', $finding->status);
        $this->assertSame($auditor->id, $finding->reported_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_finding_created', 'model_id' => $finding->id]);
    }

    public function test_recording_a_finding_does_not_change_the_record_it_is_about(): void
    {
        $fine = $this->makeFine($this->makeStudent(), ['amount' => 45]);

        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.store'), [
            'reference_type' => 'fine', 'reference_id' => $fine->id,
            'title' => 'Wrong amount', 'issue' => 'Should be 50.', 'severity' => 'medium',
        ])->assertRedirect();

        $this->assertSame('45.00', $fine->fresh()->amount);
        $this->assertSame('unpaid', $fine->fresh()->status);
    }

    public function test_a_finding_needs_a_title_issue_and_valid_severity(): void
    {
        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.store'), [
            'reference_type' => 'fine', 'title' => '', 'issue' => '', 'severity' => 'catastrophic',
        ])->assertSessionHasErrors(['title', 'issue', 'severity']);

        $this->assertSame(0, AuditFinding::count());
    }

    public function test_raising_a_finding_from_the_report_uses_the_servers_text_and_is_not_duplicated(): void
    {
        $fine = $this->makeFine($this->makeStudent('Ana'), ['amount' => 45]);
        $auditor = $this->makeAuditor();
        $payload = ['check' => 'amount_differs_from_rule', 'reference_type' => 'fine', 'reference_id' => $fine->id];

        $this->actingAs($auditor)->post(route('officer.audit.findings.from-check'), $payload)->assertRedirect();
        $this->actingAs($auditor)->post(route('officer.audit.findings.from-check'), $payload)->assertRedirect();

        $this->assertSame(1, AuditFinding::count());
        $finding = AuditFinding::first();
        $this->assertSame('amount_differs_from_rule', $finding->check_type);
        $this->assertStringContainsString('45.00', $finding->issue);
    }

    public function test_a_finding_cannot_be_raised_for_a_discrepancy_that_does_not_exist(): void
    {
        $fine = $this->makeFine($this->makeStudent()); // amount is correct: no discrepancy

        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.from-check'), [
            'check' => 'amount_differs_from_rule', 'reference_type' => 'fine', 'reference_id' => $fine->id,
        ])->assertRedirect(route('officer.audit.discrepancies'));

        $this->assertSame(0, AuditFinding::count());
    }

    public function test_an_unknown_check_name_is_rejected(): void
    {
        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.from-check'), [
            'check' => 'made_up', 'reference_type' => 'fine', 'reference_id' => 1,
        ])->assertSessionHasErrors('check');
    }

    public function test_only_an_open_finding_can_be_edited(): void
    {
        $auditor = $this->makeAuditor();
        $finding = $this->newFinding();
        $edit = ['title' => 'Edited', 'issue' => 'New text', 'severity' => 'low'];

        $this->actingAs($auditor)->patch(route('officer.audit.findings.update', $finding), $edit)->assertRedirect();
        $this->assertSame('Edited', $finding->fresh()->title);

        $finding->update(['status' => 'acknowledged']);
        $this->actingAs($auditor)->patch(route('officer.audit.findings.update', $finding), ['title' => 'Again'] + $edit)->assertStatus(422);
        $this->assertSame('Edited', $finding->fresh()->title);
    }

    public function test_withdrawing_needs_a_reason_and_keeps_the_record(): void
    {
        $auditor = $this->makeAuditor();
        $finding = $this->newFinding();

        $this->actingAs($auditor)->post(route('officer.audit.findings.withdraw', $finding), ['resolution_note' => ''])
            ->assertSessionHasErrors('resolution_note');

        $this->actingAs($auditor)->post(route('officer.audit.findings.withdraw', $finding), ['resolution_note' => 'Raised in error'])
            ->assertRedirect();

        $this->assertSame('dismissed', $finding->fresh()->status);
        $this->assertSame('Raised in error', $finding->fresh()->resolution_note);
    }

    public function test_the_auditor_can_reopen_a_resolved_finding_and_the_old_outcome_is_logged(): void
    {
        $finding = $this->newFinding(['status' => 'resolved', 'resolution_note' => 'Fixed', 'resolved_by' => $this->admin->id, 'resolved_at' => now()]);

        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.reopen', $finding), ['reason' => 'Still wrong'])
            ->assertRedirect();

        $finding->refresh();
        $this->assertSame('open', $finding->status);
        $this->assertNull($finding->resolution_note);
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_finding_reopened', 'model_id' => $finding->id]);
    }

    public function test_an_open_finding_cannot_be_reopened(): void
    {
        $this->actingAs($this->makeAuditor())->post(route('officer.audit.findings.reopen', $this->newFinding()), ['reason' => 'Why not'])
            ->assertStatus(422);
    }

    public function test_an_officer_without_the_findings_permission_cannot_create_or_edit(): void
    {
        $reader = $this->makeOfficerWith('Auditor', ['view_reports']);
        $finding = $this->newFinding();

        $this->actingAs($reader)->get(route('officer.audit.findings.index'))->assertOk(); // may read
        $this->actingAs($reader)->post(route('officer.audit.findings.store'), [
            'reference_type' => 'fine', 'title' => 'x', 'issue' => 'y', 'severity' => 'low',
        ])->assertForbidden();
        $this->actingAs($reader)->patch(route('officer.audit.findings.update', $finding), ['title' => 'x', 'issue' => 'y', 'severity' => 'low'])
            ->assertForbidden();
    }

    // ---- admin side ---------------------------------------------------

    public function test_the_admin_acknowledges_then_resolves_a_finding(): void
    {
        $finding = $this->newFinding();

        $this->actingAs($this->admin)->post(route('admin.audit-findings.respond', $finding), ['action' => 'acknowledge'])->assertRedirect();
        $this->assertSame('acknowledged', $finding->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.audit-findings.respond', $finding), [
            'action' => 'resolve', 'resolution_note' => 'Receipt book checked and corrected.',
        ])->assertRedirect();

        $finding->refresh();
        $this->assertSame('resolved', $finding->status);
        $this->assertSame($this->admin->id, $finding->resolved_by);
        $this->assertNotNull($finding->resolved_at);
    }

    public function test_resolving_needs_a_note(): void
    {
        $finding = $this->newFinding();

        $this->actingAs($this->admin)->post(route('admin.audit-findings.respond', $finding), ['action' => 'resolve'])
            ->assertSessionHasErrors('resolution_note');

        $this->assertSame('open', $finding->fresh()->status);
    }

    public function test_a_closed_finding_cannot_be_responded_to_again(): void
    {
        $finding = $this->newFinding(['status' => 'resolved']);

        $this->actingAs($this->admin)->post(route('admin.audit-findings.respond', $finding), ['action' => 'acknowledge'])->assertStatus(422);
        $this->actingAs($this->admin)->post(route('admin.audit-findings.respond', $finding), ['action' => 'resolve', 'resolution_note' => 'Again please'])->assertStatus(422);
    }

    public function test_the_auditor_cannot_resolve_their_own_finding_through_the_admin_screen(): void
    {
        $finding = $this->newFinding();

        $this->actingAs($this->makeAuditor())->post(route('admin.audit-findings.respond', $finding), [
            'action' => 'resolve', 'resolution_note' => 'Resolved by me',
        ])->assertForbidden();

        $this->assertSame('open', $finding->fresh()->status);
    }

    public function test_the_admin_findings_pages_load(): void
    {
        $finding = $this->newFinding();

        $this->actingAs($this->admin)->get(route('admin.audit-findings.index'))->assertOk()->assertSee('Duplicate receipt');
        $this->actingAs($this->admin)->get(route('admin.audit-findings.show', $finding))->assertOk()->assertSee('Same receipt used twice.');
    }
}
