<?php
// tests/Feature/Audit/AuditServiceTest.php

namespace Tests\Feature\Audit;

use App\Jobs\IssueSessionFinesJob;
use App\Models\EventFineRule;
use App\Models\Fine;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * The Auditor's checks, one at a time. Each check must flag the bad case
 * and leave the good case alone; the "job consistency" tests make sure what
 * IssueSessionFinesJob really produces is never reported as a problem.
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    /** @return array<int, string> the check names found right now */
    private function checks(): array
    {
        return app(AuditService::class)->discrepancies()->pluck('check')->all();
    }

    // ---- payments -----------------------------------------------------

    public function test_one_receipt_used_for_different_students_is_flagged(): void
    {
        $this->makeFine($this->makeStudent('Ana'), ['status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1']);
        $this->makeFine($this->makeStudent('Ben'), ['status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1']);

        $this->assertContains('duplicate_receipt', $this->checks());
    }

    public function test_one_receipt_covering_several_fines_of_the_same_student_is_fine(): void
    {
        $student = $this->makeStudent();
        $this->makeFine($student, ['status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1']);
        $this->makeFine($student, [
            'violation_type' => 'missed_morning_timeout', 'amount' => 30,
            'status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1',
        ]);

        $this->assertNotContains('duplicate_receipt', $this->checks());
    }

    public function test_a_paid_fine_without_a_receipt_is_flagged_but_one_with_a_receipt_is_not(): void
    {
        $this->makeFine($this->makeStudent('No Receipt'), ['status' => 'paid', 'cleared_by' => $this->admin->id]);
        $this->assertSame(['paid_without_receipt'], $this->checks());

        Fine::query()->update(['receipt_number' => 'OR-9']);
        $this->assertSame([], $this->checks());
    }

    public function test_a_waiver_with_a_short_reason_is_flagged_but_a_proper_reason_is_not(): void
    {
        $fine = $this->makeFine($this->makeStudent(), ['status' => 'waived', 'cleared_by' => $this->admin->id, 'reason' => 'ok']);
        $this->assertContains('weak_waiver_reason', $this->checks());

        $fine->update(['reason' => 'Excused: official school activity, approved by the adviser']);
        $this->assertNotContains('weak_waiver_reason', $this->checks());
    }

    public function test_a_settled_fine_with_no_officer_recorded_is_flagged(): void
    {
        $this->makeFine($this->makeStudent(), ['status' => 'paid', 'receipt_number' => 'OR-5']);

        $this->assertContains('settled_without_officer', $this->checks());
    }

    // ---- fines versus rules and attendance ---------------------------

    public function test_a_fine_amount_that_differs_from_the_rule_is_flagged(): void
    {
        $fine = $this->makeFine($this->makeStudent(), ['amount' => 45]);
        $this->assertContains('amount_differs_from_rule', $this->checks());

        $fine->update(['amount' => 50]);
        $this->assertNotContains('amount_differs_from_rule', $this->checks());
    }

    public function test_a_fine_for_a_scan_the_student_made_is_flagged(): void
    {
        $student = $this->makeStudent();
        $this->makeFine($student);
        $this->assertNotContains('fine_despite_attendance', $this->checks());

        $this->recordScan($student, 'time_in', 'present', ['is_manual_override' => true]);
        $this->assertContains('fine_despite_attendance', $this->checks());
    }

    public function test_a_fine_for_a_different_scan_than_the_one_attended_is_not_flagged(): void
    {
        $student = $this->makeStudent();
        $this->recordScan($student, 'time_in');
        $this->makeFine($student, ['violation_type' => 'missed_morning_timeout', 'amount' => 30]);

        $this->assertNotContains('fine_despite_attendance', $this->checks());
    }

    public function test_an_expected_fine_that_is_missing_is_flagged_for_each_student_and_scan(): void
    {
        $this->makeStudent('Ana');
        $this->makeStudent('Ben');
        $this->session->update(['fines_issued' => true]);

        $missing = app(AuditService::class)->discrepancies()->where('check', 'missing_fine');

        $this->assertCount(4, $missing); // 2 students x (time-in + time-out)
    }

    public function test_a_session_whose_fines_were_not_issued_yet_is_not_audited_for_missing_fines(): void
    {
        $this->makeStudent();

        $this->assertNotContains('missing_fine', $this->checks());
    }

    public function test_a_student_registered_after_the_session_closed_is_not_expected_to_be_fined(): void
    {
        $this->makeStudent('Late Joiner', ['created_at' => now()]);
        $this->session->update(['fines_issued' => true]);

        $this->assertNotContains('missing_fine', $this->checks());
    }

    // ---- consistency with IssueSessionFinesJob -----------------------

    public function test_what_the_fines_job_issues_is_never_reported_as_a_discrepancy(): void
    {
        $present = $this->makeStudent('Present Student');
        $this->makeStudent('Absent Student');
        $this->recordScan($present, 'time_in');
        $this->recordScan($present, 'time_out');

        IssueSessionFinesJob::dispatchSync($this->session->id);

        $this->assertSame(2, Fine::count()); // only the absent student: time-in + time-out
        $this->assertSame([], $this->checks());
    }

    public function test_a_parade_session_the_job_processed_has_no_discrepancies(): void
    {
        $this->session->update(['is_parade' => true, 'timeout_start' => null, 'timeout_end' => null]);
        EventFineRule::create(['event_id' => $this->event->id, 'violation_type' => 'missed_parade', 'amount' => 250]);
        $this->makeStudent('Absent At Parade');

        IssueSessionFinesJob::dispatchSync($this->session->id);

        $this->assertDatabaseHas('fines', ['violation_type' => 'missed_parade', 'amount' => 250]);
        $this->assertSame([], $this->checks());
    }

    public function test_students_outside_a_programme_targeted_event_are_not_expected_to_be_fined(): void
    {
        $this->event->update(['target_programs' => ['BS Information Technology']]);
        $this->makeStudent('Other Programme', ['program' => 'BS Business Administration']);

        IssueSessionFinesJob::dispatchSync($this->session->id);

        $this->assertSame(0, Fine::count());
        $this->assertSame([], $this->checks());
    }

    // ---- flagged scans ------------------------------------------------

    public function test_a_flagged_scan_is_excused_from_fines_and_not_reported_while_recent(): void
    {
        $student = $this->makeStudent('Flagged Student');
        $this->recordScan($student, 'time_in', 'flagged_for_review', ['scanned_at' => now()->subHours(2)]);
        $this->recordScan($student, 'time_out', 'flagged_for_review', ['scanned_at' => now()->subHours(2)]);

        IssueSessionFinesJob::dispatchSync($this->session->id);

        $this->assertSame(0, Fine::count());
        $this->assertSame([], $this->checks());
    }

    public function test_a_flagged_scan_left_unreviewed_for_days_is_reported_as_stale(): void
    {
        $student = $this->makeStudent();
        $this->recordScan($student, 'time_in', 'flagged_for_review', ['scanned_at' => now()->subDays(3)]);

        $this->assertContains('flagged_scan_stale', $this->checks());
    }

    // ---- summary ------------------------------------------------------

    public function test_the_summary_totals_fines_by_status(): void
    {
        $ana = $this->makeStudent('Ana');
        $this->makeFine($ana);
        $this->makeFine($ana, ['violation_type' => 'missed_morning_timeout', 'amount' => 30, 'status' => 'paid', 'cleared_by' => $this->admin->id, 'receipt_number' => 'OR-1']);
        $this->makeFine($this->makeStudent('Ben'), ['status' => 'waived', 'cleared_by' => $this->admin->id, 'reason' => 'Excused: official school activity']);

        $summary = app(AuditService::class)->summary();

        $this->assertSame(3, $summary['assessed_count']);
        $this->assertSame(130.0, $summary['assessed_total']);
        $this->assertSame(30.0, $summary['collected_total']);
        $this->assertSame(50.0, $summary['outstanding_total']);
        $this->assertSame(50.0, $summary['waived_total']);
    }

    public function test_discrepancies_are_sorted_most_severe_first(): void
    {
        $this->makeFine($this->makeStudent('A'), ['status' => 'paid', 'cleared_by' => $this->admin->id]);            // low
        $this->makeFine($this->makeStudent('B'), ['status' => 'paid', 'receipt_number' => 'OR-7']);                   // high
        $this->makeFine($this->makeStudent('C'), ['amount' => 45]);                                                   // medium

        $severities = app(AuditService::class)->discrepancies()->pluck('severity')->all();

        $this->assertSame(['high', 'medium', 'low'], $severities);
    }
}
