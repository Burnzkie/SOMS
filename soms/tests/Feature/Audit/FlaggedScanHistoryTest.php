<?php
// tests/Feature/Audit/FlaggedScanHistoryTest.php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Reviewing a flagged scan overwrites its status, so who decided and when
 * is kept on the row, where the Auditor can read it.
 */
class FlaggedScanHistoryTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    private function reviewer()
    {
        return $this->makeOfficerWith('Secretary', ['manage_attendance']);
    }

    public function test_approving_records_who_decided_when_and_what(): void
    {
        $reviewer = $this->reviewer();
        $scan = $this->recordScan($this->makeStudent(), 'time_in', 'flagged_for_review');

        $this->actingAs($reviewer)->post(route('officer.attendance.review', $scan), ['decision' => 'approve'])->assertRedirect();

        $scan->refresh();
        $this->assertSame('present', $scan->status);
        $this->assertSame('approved', $scan->review_decision);
        $this->assertSame($reviewer->id, $scan->reviewed_by);
        $this->assertNotNull($scan->reviewed_at);
    }

    public function test_rejecting_records_the_decision_too(): void
    {
        $reviewer = $this->reviewer();
        $scan = $this->recordScan($this->makeStudent(), 'time_in', 'flagged_for_review');

        $this->actingAs($reviewer)->post(route('officer.attendance.review', $scan), ['decision' => 'reject'])->assertRedirect();

        $scan->refresh();
        $this->assertSame('absent', $scan->status);
        $this->assertSame('rejected', $scan->review_decision);
        $this->assertSame($reviewer->id, $scan->reviewed_by);
    }

    public function test_a_scan_that_was_never_flagged_has_no_review_history(): void
    {
        $scan = $this->recordScan($this->makeStudent());

        $this->assertNull($scan->review_decision);
        $this->assertNull($scan->reviewed_by);
    }

    public function test_the_auditor_sees_the_decision_and_the_reviewer_on_the_attendance_page(): void
    {
        $reviewer = $this->reviewer();
        $scan = $this->recordScan($this->makeStudent('Flagged Fiona'), 'time_in', 'flagged_for_review');
        $this->actingAs($reviewer)->post(route('officer.attendance.review', $scan), ['decision' => 'approve']);

        $this->actingAs($this->makeAuditor())->get(route('officer.audit.attendance'))
            ->assertOk()
            ->assertSee('Flagged Fiona')
            ->assertSee('Approved')
            ->assertSee($reviewer->name);
    }

    public function test_manual_overrides_are_listed_with_who_and_why(): void
    {
        $officer = $this->reviewer();
        $this->recordScan($this->makeStudent('Override Olga'), 'time_in', 'present', [
            'is_manual_override' => true, 'override_reason' => 'Phone battery died', 'marked_by' => $officer->id,
        ]);

        $this->actingAs($this->makeAuditor())->get(route('officer.audit.attendance'))
            ->assertOk()
            ->assertSee('Override Olga')
            ->assertSee('Phone battery died')
            ->assertSee($officer->name);
    }
}
