<?php
// tests/Feature/Audit/AuditMobileRecordsTest.php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\AuditFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Mobile API for the Auditor's attendance records and activity log, and for
 * the Admin's response to findings. Same rules as the web pages.
 */
class AuditMobileRecordsTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    // ---- attendance ----------------------------------------------------

    public function test_attendance_totals_flagged_history_and_overrides_are_returned(): void
    {
        $reviewer = $this->makeOfficerWith('Secretary', ['manage_attendance']);
        $this->recordScan($this->makeStudent('Plain Pia'));
        $this->recordScan($this->makeStudent('Pending Pat'), 'time_in', 'flagged_for_review');
        $this->recordScan($this->makeStudent('Approved Ana'), 'time_in', 'present', [
            'review_decision' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
        ]);
        $this->recordScan($this->makeStudent('Rejected Rey'), 'time_in', 'absent', [
            'review_decision' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
        ]);
        $this->recordScan($this->makeStudent('Override Olga'), 'time_in', 'present', [
            'is_manual_override' => true, 'override_reason' => 'Phone battery died', 'marked_by' => $reviewer->id,
        ]);
        $this->makeFine($this->makeStudent('Fined Fay'));

        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_attendance']), ['*']);
        $response = $this->getJson('/api/v1/officer/audit/attendance')->assertOk();

        $row = $response->json('data.sessions.data.0');
        $this->assertSame('Audit Test Event', $row['event']);
        // Plain scan + the manual override. The approved scan is counted under 'approved'.
        $this->assertSame(2, $row['present']);
        $this->assertSame(1, $row['pending_flagged']);
        $this->assertSame(1, $row['approved']);
        $this->assertSame(1, $row['rejected']);
        $this->assertSame(1, $row['overrides']);
        $this->assertSame(1, $row['fines']);

        $history = collect($response->json('data.flagged_history'));
        $this->assertEqualsCanonicalizing(['Pending Pat', 'Approved Ana', 'Rejected Rey'], $history->pluck('student_name')->all());
        $this->assertSame('approved', $history->firstWhere('student_name', 'Approved Ana')['decision']);
        $this->assertSame($reviewer->name, $history->firstWhere('student_name', 'Approved Ana')['reviewed_by']);
        $this->assertNull($history->firstWhere('student_name', 'Pending Pat')['decision']);

        $override = $response->json('data.overrides.0');
        $this->assertSame('Override Olga', $override['student_name']);
        $this->assertSame('Phone battery died', $override['reason']);
        $this->assertSame($reviewer->name, $override['marked_by']);
    }

    public function test_attendance_needs_the_view_attendance_permission(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_fines', 'view_reports', 'view_audit_logs']), ['*']);
        $this->getJson('/api/v1/officer/audit/attendance')->assertForbidden();

        Sanctum::actingAs($this->makeStudent(), ['*']);
        $this->getJson('/api/v1/officer/audit/attendance')->assertForbidden();
    }

    // ---- activity log --------------------------------------------------

    public function test_the_activity_log_returns_rows_and_only_the_masked_ip(): void
    {
        ActivityLog::record($this->admin->id, 'privacy_probe', null, null, ['note' => 'hello'], '203.0.113.45');
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_audit_logs']), ['*']);

        $response = $this->getJson('/api/v1/officer/audit/activity-log')->assertOk();

        $row = collect($response->json('data.logs.data'))->firstWhere('action', 'privacy_probe');
        $this->assertSame($this->admin->name, $row['user']);
        $this->assertStringContainsString('hello', $row['details']);
        $this->assertStringNotContainsString('203.0.113.45', $response->getContent());
        $this->assertIsBool($response->json('data.chain_ok'));
        $this->assertContains('privacy_probe', $response->json('data.actions'));
    }

    public function test_the_activity_log_can_be_filtered_by_action(): void
    {
        ActivityLog::record($this->admin->id, 'alpha_action');
        ActivityLog::record($this->admin->id, 'beta_action');
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_audit_logs']), ['*']);

        $rows = collect($this->getJson('/api/v1/officer/audit/activity-log?action=beta_action')->assertOk()->json('data.logs.data'));

        $this->assertSame(['beta_action'], $rows->pluck('action')->unique()->values()->all());
    }

    public function test_a_bad_date_filter_is_rejected(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_audit_logs']), ['*']);

        $this->getJson('/api/v1/officer/audit/activity-log?from=not-a-date')->assertStatus(422)->assertJsonValidationErrors('from');
    }

    public function test_the_activity_log_needs_its_own_permission(): void
    {
        Sanctum::actingAs($this->makeOfficerWith('Auditor', ['view_attendance', 'view_fines', 'view_reports']), ['*']);

        $this->getJson('/api/v1/officer/audit/activity-log')->assertForbidden();
    }

    // ---- admin: findings -----------------------------------------------

    private function newFinding(array $overrides = []): AuditFinding
    {
        return AuditFinding::create($overrides + [
            'organization_id' => $this->org->id, 'reference_type' => 'fine', 'reference_id' => 1,
            'title' => 'Duplicate receipt', 'issue' => 'Same receipt used twice.', 'severity' => 'high',
            'status' => 'open', 'reported_by' => $this->makeAuditor()->id, 'reported_at' => now(),
        ]);
    }

    public function test_the_admin_lists_findings_with_filters_and_the_active_count(): void
    {
        $this->newFinding(['title' => 'Open one']);
        $this->newFinding(['title' => 'Closed one', 'status' => 'resolved']);
        Sanctum::actingAs($this->admin, ['*']);

        $all = $this->getJson('/api/v1/admin/audit-findings')->assertOk();
        $this->assertCount(2, $all->json('data.data'));
        $this->assertSame(1, $all->json('meta.active_count'));

        $open = $this->getJson('/api/v1/admin/audit-findings?status=open')->assertOk();
        $this->assertSame(['Open one'], collect($open->json('data.data'))->pluck('title')->all());
    }

    public function test_the_admin_acknowledges_then_resolves_through_the_api(): void
    {
        $finding = $this->newFinding();
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson("/api/v1/admin/audit-findings/{$finding->id}/respond", ['action' => 'acknowledge'])
            ->assertOk()->assertJsonPath('data.status', 'acknowledged');

        $this->postJson("/api/v1/admin/audit-findings/{$finding->id}/respond", ['action' => 'resolve', 'resolution_note' => 'Receipt book corrected.'])
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolver.name', $this->admin->name);

        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_finding_acknowledged', 'model_id' => $finding->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_finding_resolved', 'model_id' => $finding->id]);
    }

    public function test_the_api_enforces_the_same_transition_rules_as_the_web(): void
    {
        $closed = $this->newFinding(['status' => 'resolved']);
        $open = $this->newFinding();
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson("/api/v1/admin/audit-findings/{$closed->id}/respond", ['action' => 'acknowledge'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson("/api/v1/admin/audit-findings/{$closed->id}/respond", ['action' => 'resolve', 'resolution_note' => 'Again please'])
            ->assertStatus(422);
        $this->postJson("/api/v1/admin/audit-findings/{$open->id}/respond", ['action' => 'resolve'])
            ->assertStatus(422)->assertJsonValidationErrors('resolution_note');

        $this->assertSame('open', $open->fresh()->status);
    }

    public function test_only_the_admin_can_use_the_admin_findings_api(): void
    {
        $finding = $this->newFinding();

        foreach ([$this->makeAuditor(), $this->makeTreasurer(), $this->makeStudent()] as $user) {
            Sanctum::actingAs($user, ['*']);
            $this->getJson('/api/v1/admin/audit-findings')->assertForbidden();
            $this->postJson("/api/v1/admin/audit-findings/{$finding->id}/respond", ['action' => 'acknowledge'])->assertForbidden();
        }

        $this->assertSame('open', $finding->fresh()->status);
    }
}
