<?php
// tests/Feature/Audit/AuditApiTest.php

namespace Tests\Feature\Audit;

use App\Models\AuditFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/** The Auditor workspace as JSON for the mobile app: same checks, same permissions. */
class AuditApiTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    public function test_the_auditor_reads_the_summary_fines_and_discrepancies(): void
    {
        $this->makeFine($this->makeStudent('Ana Cruz'), ['amount' => 45]);
        Sanctum::actingAs($this->makeAuditor(), ['*']);

        $this->getJson('/api/v1/officer/audit/summary')
            ->assertOk()
            ->assertJsonPath('data.assessed_count', 1)
            ->assertJsonPath('data.outstanding_total', 45);

        $this->getJson('/api/v1/officer/audit/fines')
            ->assertOk()
            ->assertJsonPath('data.data.0.student_name', 'Ana Cruz')
            ->assertJsonPath('data.data.0.status', 'unpaid');

        $this->getJson('/api/v1/officer/audit/discrepancies')
            ->assertOk()
            ->assertJsonPath('data.0.check', 'amount_differs_from_rule')
            ->assertJsonPath('data.0.check_label', 'Fine amount differs from the fine rule');
    }

    public function test_the_fines_list_can_be_filtered_by_status_and_receipt(): void
    {
        $this->makeFine($this->makeStudent('Unpaid Una'));
        $this->makeFine($this->makeStudent('Paid Pia'), ['status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-123']);
        Sanctum::actingAs($this->makeAuditor(), ['*']);

        $this->getJson('/api/v1/officer/audit/fines?status=paid')->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/officer/audit/fines?q=OR-123')->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.student_name', 'Paid Pia');
    }

    public function test_the_auditor_records_and_lists_findings(): void
    {
        Sanctum::actingAs($this->makeAuditor(), ['*']);

        $this->postJson('/api/v1/officer/audit/findings', [
            'reference_type' => 'fine', 'reference_id' => 3, 'title' => 'Wrong amount', 'issue' => 'Should be 50', 'severity' => 'medium',
        ])->assertCreated()->assertJsonPath('data.status', 'open');

        $this->getJson('/api/v1/officer/audit/findings')->assertOk()->assertJsonPath('data.data.0.title', 'Wrong amount');
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_finding_created']);
    }

    public function test_an_invalid_finding_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAuditor(), ['*']);

        $this->postJson('/api/v1/officer/audit/findings', ['reference_type' => 'nonsense', 'title' => '', 'issue' => '', 'severity' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference_type', 'title', 'issue', 'severity']);

        $this->assertSame(0, AuditFinding::count());
    }

    public function test_each_endpoint_checks_its_own_permission(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_fines']), ['*']);

        $this->getJson('/api/v1/officer/audit/fines')->assertOk();
        $this->getJson('/api/v1/officer/audit/summary')->assertForbidden();
        $this->getJson('/api/v1/officer/audit/discrepancies')->assertForbidden();
        $this->getJson('/api/v1/officer/audit/findings')->assertForbidden();
        $this->postJson('/api/v1/officer/audit/findings', [
            'reference_type' => 'fine', 'title' => 'x', 'issue' => 'y', 'severity' => 'low',
        ])->assertForbidden();
    }

    public function test_an_officer_without_audit_permissions_and_a_student_are_refused(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Secretary', ['manage_events']), ['*']);
        $this->getJson('/api/v1/officer/audit/summary')->assertForbidden();

        Sanctum::actingAs($this->makeStudent(), ['*']);
        $this->getJson('/api/v1/officer/audit/summary')->assertForbidden();
    }

    public function test_the_dashboard_summary_lists_the_permissions_the_officer_holds(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_fines', 'manage_audit_findings']), ['*']);

        $response = $this->getJson('/api/v1/officer/dashboard-summary')->assertOk();

        $this->assertEqualsCanonicalizing(['view_fines', 'manage_audit_findings'], $response->json('data.permissions'));
        $response->assertJsonStructure(['data' => ['active_members', 'permissions']]);
    }

    public function test_an_officer_with_no_permissions_gets_an_empty_list(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Secretary', []), ['*']);

        $this->getJson('/api/v1/officer/dashboard-summary')->assertOk()->assertJsonPath('data.permissions', []);
    }

    public function test_requests_without_a_token_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/officer/audit/summary')->assertUnauthorized();
    }
}
