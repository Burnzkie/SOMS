<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OfficerPosition;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeActiveOfficer(string $positionTitle = 'Secretary', array $permissions = []): array
    {
        $org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $officer = User::factory()->officer()->create();

        $position = OfficerPosition::create([
            'user_id' => $officer->id,
            'organization_id' => $org->id,
            'position_title' => $positionTitle,
            'permissions' => $permissions,
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        return [$officer, $position];
    }

    /**
     * The core bug this batch fixed: previously there was no web route or
     * button for revoking an officer at all -- only the API had it. This
     * exercises the full stack we added: route -> controller -> policy.
     */
    public function test_admin_can_revoke_an_active_officer_from_the_web_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer('Secretary');

        $response = $this->actingAs($admin)
            ->post(route('admin.officers.revoke', $position));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertFalse($position->fresh()->is_active);
        $this->assertSame('student', $officer->fresh()->role);
    }

    /**
     * Ties the revoke fix directly to permission checks: once revoked,
     * the officer must immediately lose every permission admin had
     * checked for them, not just have their role column changed.
     */
    public function test_revoked_officer_immediately_loses_granted_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer('Public Relations Officer', ['draft_announcements', 'view_calendar']);

        $this->assertTrue(OfficerPermission::can($officer->fresh(), 'draft_announcements'));

        $this->actingAs($admin)->post(route('admin.officers.revoke', $position));

        $revoked = $officer->fresh();
        $this->assertFalse(OfficerPermission::can($revoked, 'draft_announcements'));
        $this->assertFalse(OfficerPermission::can($revoked, 'view_calendar'));
    }

    public function test_revoking_an_already_inactive_position_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer();
        $position->update(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->post(route('admin.officers.revoke', $position));

        $response->assertStatus(422);
    }

    public function test_a_regular_student_cannot_revoke_an_officer(): void
    {
        $student = User::factory()->create();
        [$officer, $position] = $this->makeActiveOfficer();

        $response = $this->actingAs($student)
            ->post(route('admin.officers.revoke', $position));

        $response->assertForbidden();
        $this->assertTrue($position->fresh()->is_active);
    }

    public function test_revocation_is_written_to_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer('Auditor');

        $this->actingAs($admin)->post(route('admin.officers.revoke', $position));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'officer_revoked',
            'model_id' => $position->id,
        ]);
    }
}
