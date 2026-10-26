<?php
// tests/Feature/Audit/RolePresetTest.php

namespace Tests\Feature\Audit;

use App\Models\OfficerPosition;
use App\Support\OfficerPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * Recommended access per position. The matrix tests guard separation of
 * duties; the appointment tests check presets are applied (and only when
 * no explicit list is sent); the end-to-end tests check an officer
 * appointed with a preset can really do their job, and nothing else.
 */
class RolePresetTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    private const TITLES = ['President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor', 'Public Relations Officer'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    private function holders(string $permission): array
    {
        return collect(OfficerPermission::PRESETS)
            ->filter(fn (array $set) => in_array($permission, $set, true))
            ->keys()
            ->sort()
            ->values()
            ->all();
    }

    // ---- the matrix ----------------------------------------------------

    public function test_every_appointable_position_has_a_preset_of_valid_permissions(): void
    {
        foreach (self::TITLES as $title) {
            $this->assertArrayHasKey($title, OfficerPermission::PRESETS, "No preset for {$title}");
            $this->assertSame([], array_diff(OfficerPermission::presetFor($title), array_keys(OfficerPermission::PERMISSIONS)), "{$title} preset has an unknown permission key");
            $this->assertSame(array_values(array_unique(OfficerPermission::presetFor($title))), OfficerPermission::presetFor($title), "{$title} preset has duplicates");
            $this->assertContains('view_dashboard', OfficerPermission::presetFor($title));
        }
        $this->assertSame([], OfficerPermission::presetFor('Janitor'));
    }

    public function test_only_the_auditor_can_record_findings_or_read_the_activity_log(): void
    {
        $this->assertSame(['Auditor'], $this->holders('manage_audit_findings'));
        $this->assertSame(['Auditor'], $this->holders('view_audit_logs'));
    }

    public function test_the_auditor_has_no_power_to_change_records(): void
    {
        $changing = ['manage_events', 'manage_attendance', 'manage_announcements', 'draft_announcements', 'manage_members', 'manage_calendar'];

        $this->assertSame([], array_values(array_intersect(OfficerPermission::presetFor('Auditor'), $changing)));
    }

    public function test_only_the_president_and_pio_can_publish_announcements(): void
    {
        $this->assertSame(['President', 'Public Relations Officer'], $this->holders('manage_announcements'));
    }

    public function test_only_the_secretary_scans_attendance_and_leaders_do_not(): void
    {
        $this->assertSame(['Secretary'], $this->holders('manage_attendance'));
    }

    public function test_leaders_and_the_auditor_can_see_fines_but_the_treasurer_gets_that_from_the_title(): void
    {
        $this->assertSame(['Auditor', 'President', 'Vice President'], $this->holders('view_fines'));
        $this->assertNotContains('view_fines', OfficerPermission::presetFor('Treasurer'));
    }

    public function test_reports_and_findings_are_visible_to_leaders_treasurer_and_auditor_only(): void
    {
        $this->assertSame(['Auditor', 'President', 'Treasurer', 'Vice President'], $this->holders('view_reports'));
        $this->assertNotContains('view_reports', OfficerPermission::presetFor('Secretary'));
        $this->assertNotContains('view_reports', OfficerPermission::presetFor('Public Relations Officer'));
    }

    public function test_the_secretary_keeps_event_access_so_the_scan_station_stays_reachable(): void
    {
        // The web event pages are gated by manage_events and lead to the scan station.
        $this->assertContains('manage_events', OfficerPermission::presetFor('Secretary'));
        $this->assertContains('manage_attendance', OfficerPermission::presetFor('Secretary'));
    }

    // ---- appointment ---------------------------------------------------

    private function appointOnWeb(string $title, ?array $permissions = null)
    {
        $student = $this->makeStudent("Appointee {$title}");
        $payload = ['user_id' => $student->id, 'position_title' => $title, 'academic_year' => '2026-2027'];
        if ($permissions !== null) {
            $payload['permissions'] = $permissions;
        }

        $this->actingAs($this->admin)->post(route('admin.officers.appoint'), $payload)->assertRedirect();

        return OfficerPosition::where('user_id', $student->id)->firstOrFail();
    }

    public function test_appointing_on_the_web_gives_the_recommended_access_for_the_position(): void
    {
        foreach (self::TITLES as $title) {
            $position = $this->appointOnWeb($title);

            $this->assertEqualsCanonicalizing(OfficerPermission::presetFor($title), $position->permissions, $title);
        }
    }

    public function test_an_explicit_permission_list_is_used_exactly_as_sent(): void
    {
        $position = $this->appointOnWeb('Auditor', ['view_calendar']);

        $this->assertSame(['view_calendar'], $position->permissions);
    }

    public function test_the_api_applies_the_preset_only_when_no_list_is_sent(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $year = '2026-2027';

        $one = $this->makeStudent('API One');
        $this->postJson('/api/v1/admin/officers/appoint', ['user_id' => $one->id, 'position_title' => 'Treasurer', 'academic_year' => $year])->assertOk();
        $this->assertEqualsCanonicalizing(OfficerPermission::presetFor('Treasurer'), OfficerPosition::where('user_id', $one->id)->value('permissions'));

        $two = $this->makeStudent('API Two');
        $this->postJson('/api/v1/admin/officers/appoint', ['user_id' => $two->id, 'position_title' => 'Secretary', 'academic_year' => $year, 'permissions' => []])->assertOk();
        $this->assertSame([], OfficerPosition::where('user_id', $two->id)->value('permissions'));

        $three = $this->makeStudent('API Three');
        $this->postJson('/api/v1/admin/officers/appoint', ['user_id' => $three->id, 'position_title' => 'President', 'academic_year' => $year, 'permissions' => ['view_dashboard']])->assertOk();
        $this->assertSame(['view_dashboard'], OfficerPosition::where('user_id', $three->id)->value('permissions'));
    }

    public function test_the_api_sends_the_presets_to_the_mobile_app(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson('/api/v1/admin/officers')
            ->assertOk()
            ->assertJsonPath('data.permissionPresets.Auditor', OfficerPermission::presetFor('Auditor'))
            ->assertJsonCount(6, 'data.permissionPresets');
    }

    public function test_the_permissions_screen_offers_apply_recommended_for_the_position(): void
    {
        $position = $this->appointOnWeb('Auditor', []);

        $this->actingAs($this->admin)->get(route('admin.permissions.edit', $position))
            ->assertOk()
            ->assertSee('Apply recommended for Auditor')
            ->assertSee('manage_audit_findings');
    }

    public function test_applying_recommended_is_not_saved_until_the_admin_saves(): void
    {
        $position = $this->appointOnWeb('Auditor', []);

        $this->actingAs($this->admin)->get(route('admin.permissions.edit', $position))->assertOk();

        $this->assertSame([], $position->fresh()->permissions);
    }

    // ---- end to end: an officer appointed with a preset can do the job, and no more ----

    public function test_an_appointed_auditor_can_audit_but_not_change_anything(): void
    {
        $position = $this->appointOnWeb('Auditor');
        $auditor = $position->user;
        $fine = $this->makeFine($this->makeStudent());

        foreach (['officer.audit.dashboard', 'officer.audit.fines', 'officer.audit.attendance', 'officer.audit.discrepancies', 'officer.audit.activity-log', 'officer.audit.findings.create'] as $route) {
            $this->actingAs($auditor)->get(route($route))->assertOk();
        }
        $this->actingAs($auditor)->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person'])->assertForbidden();
        $this->actingAs($auditor)->get(route('officer.events.index'))->assertForbidden();
    }

    public function test_an_appointed_secretary_reaches_the_scan_station_but_not_the_audit_area(): void
    {
        $secretary = $this->appointOnWeb('Secretary')->user;

        $this->actingAs($secretary)->get(route('officer.events.index'))->assertOk();
        $this->actingAs($secretary)->get(route('officer.attendance.station', $this->session))->assertOk();
        $this->actingAs($secretary)->get(route('officer.audit.discrepancies'))->assertForbidden();
    }

    public function test_an_appointed_president_can_read_the_audit_but_cannot_scan_or_settle_fines(): void
    {
        $president = $this->appointOnWeb('President')->user;
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($president)->get(route('officer.audit.fines'))->assertOk();
        $this->actingAs($president)->get(route('officer.audit.discrepancies'))->assertOk();
        $this->actingAs($president)->get(route('officer.audit.activity-log'))->assertForbidden();
        $this->actingAs($president)->get(route('officer.attendance.station', $this->session))->assertForbidden();
        $this->actingAs($president)->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person'])->assertForbidden();
    }

    public function test_an_appointed_treasurer_manages_fines_and_reads_the_audit_report(): void
    {
        $treasurer = $this->appointOnWeb('Treasurer')->user;
        $fine = $this->makeFine($this->makeStudent());

        $this->actingAs($treasurer)->get(route('officer.fines.index'))->assertOk();
        $this->actingAs($treasurer)->post(route('officer.fines.clear', $fine), ['reason' => 'Paid in person', 'receipt_number' => 'OR-1'])->assertRedirect();
        $this->actingAs($treasurer)->get(route('officer.audit.discrepancies'))->assertOk();
        $this->actingAs($treasurer)->post(route('officer.audit.findings.store'), ['reference_type' => 'fine', 'title' => 'x', 'issue' => 'y', 'severity' => 'low'])->assertForbidden();
    }

    public function test_an_appointed_pio_can_publish_announcements_but_not_see_fines(): void
    {
        $pio = $this->appointOnWeb('Public Relations Officer')->user;

        $this->actingAs($pio)->get(route('officer.announcements.index'))->assertOk();
        $this->actingAs($pio)->get(route('officer.audit.fines'))->assertForbidden();
        $this->actingAs($pio)->get(route('officer.audit.discrepancies'))->assertForbidden();
    }
}
