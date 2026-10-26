<?php
// tests/Feature/Audit/AuditAccessTest.php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Who can open what. The Auditor reads records but can't change them, and
 * every audit page checks its own permission: holding one audit permission
 * never opens another page.
 */
class AuditAccessTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    public function test_the_auditor_can_open_every_audit_page(): void
    {
        $auditor = $this->makeAuditor();

        foreach ([
            'officer.audit.dashboard', 'officer.audit.fines', 'officer.audit.attendance', 'officer.audit.discrepancies',
            'officer.audit.activity-log', 'officer.audit.findings.index', 'officer.audit.findings.create',
        ] as $route) {
            $this->actingAs($auditor)->get(route($route))->assertOk();
        }
    }

    public function test_each_audit_page_needs_its_own_permission(): void
    {
        $pages = [
            'view_reports'    => ['officer.audit.dashboard', 'officer.audit.discrepancies'],
            'view_fines'      => ['officer.audit.fines'],
            'view_attendance' => ['officer.audit.attendance'],
            'view_audit_logs' => ['officer.audit.activity-log'],
        ];

        foreach ($pages as $permission => $routes) {
            // An officer with only THIS permission can open its pages...
            $holder = $this->makeOfficerWith('Auditor', [$permission]);
            foreach ($routes as $route) {
                $this->actingAs($holder)->get(route($route))->assertOk();
            }

            // ...and nothing else in the audit area.
            foreach (collect($pages)->except($permission)->flatten() as $other) {
                $this->actingAs($holder)->get(route($other))->assertForbidden();
            }
        }
    }

    public function test_an_officer_without_audit_permissions_is_refused_everywhere(): void
    {
        $secretary = $this->makeOfficerWith('Secretary', ['manage_events', 'manage_attendance', 'view_dashboard']);

        foreach (['officer.audit.dashboard', 'officer.audit.fines', 'officer.audit.attendance',
                  'officer.audit.discrepancies', 'officer.audit.activity-log', 'officer.audit.findings.index'] as $route) {
            $this->actingAs($secretary)->get(route($route))->assertForbidden();
        }
    }

    public function test_the_treasurer_does_not_get_audit_access_from_the_title(): void
    {
        $this->actingAs($this->makeTreasurer())->get(route('officer.audit.discrepancies'))->assertForbidden();
    }

    public function test_students_and_admins_cannot_use_the_officer_audit_pages(): void
    {
        $this->actingAs($this->makeStudent())->get(route('officer.audit.dashboard'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('officer.audit.dashboard'))->assertForbidden();
    }

    public function test_the_auditor_cannot_change_fines_or_review_scans(): void
    {
        $auditor = $this->makeAuditor();
        $student = $this->makeStudent();
        $fine = $this->makeFine($student);
        $scan = $this->recordScan($student, 'time_in', 'flagged_for_review');

        $this->actingAs($auditor)->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person'])->assertForbidden();
        $this->actingAs($auditor)->post(route('officer.fines.waive', $fine), ['reason' => 'Waived by me'])->assertForbidden();
        $this->actingAs($auditor)->get(route('officer.fines.index'))->assertForbidden();
        $this->actingAs($auditor)->post(route('officer.attendance.review', $scan), ['decision' => 'approve'])->assertForbidden();

        $this->assertSame('unpaid', $fine->fresh()->status);
        $this->assertSame('flagged_for_review', $scan->fresh()->status);
    }

    // ---- sidebar ------------------------------------------------------

    public function test_the_sidebar_shows_audit_links_on_every_officer_page_for_the_auditor(): void
    {
        $auditor = $this->makeAuditor();

        foreach (['officer.audit.fines', 'officer.audit.findings.index', 'officer.dashboard'] as $route) {
            $this->actingAs($auditor)->get(route($route))
                ->assertOk()
                ->assertSee(route('officer.audit.discrepancies'), false)
                ->assertSee(route('officer.audit.activity-log'), false);
        }
    }

    public function test_the_sidebar_hides_audit_links_from_officers_without_audit_permissions(): void
    {
        $this->actingAs($this->makeOfficerWith('Secretary', ['view_dashboard']))
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertDontSee(route('officer.audit.discrepancies'), false);
    }

    public function test_the_treasurers_fines_link_is_no_longer_hidden_on_the_fines_page(): void
    {
        // Before the sidebar fix, only the dashboard passed permissions, so the
        // Fines page hid its own link in the sidebar.
        $this->actingAs($this->makeTreasurer())
            ->get(route('officer.fines.index'))
            ->assertOk()
            ->assertSee('href="' . route('officer.fines.index') . '"', false);
    }

    // ---- activity log privacy ----------------------------------------

    public function test_the_audit_activity_log_shows_only_the_masked_ip(): void
    {
        ActivityLog::record($this->admin->id, 'privacy_probe', null, null, null, '203.0.113.45');

        $this->actingAs($this->makeAuditor())
            ->get(route('officer.audit.activity-log'))
            ->assertOk()
            ->assertSee('privacy_probe')
            ->assertDontSee('203.0.113.45');
    }
}
