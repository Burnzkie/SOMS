<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OfficerPosition;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOfficer(string $positionTitle, array $permissions = [], bool $isActive = true): User
    {
        $org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $user = User::factory()->officer()->create();

        OfficerPosition::create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'position_title' => $positionTitle,
            'permissions' => $permissions,
            'academic_year' => '2026-2027',
            'is_active' => $isActive,
            'appointed_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Regression test: can() must deny for a user with no active officer
     * position at all — no implicit access for any position title.
     */
    public function test_can_denies_permission_for_a_user_with_no_active_position(): void
    {
        $student = User::factory()->create();

        $this->assertFalse(OfficerPermission::can($student, 'draft_announcements'));
        $this->assertFalse(OfficerPermission::can($student, 'view_dashboard'));
        $this->assertFalse(OfficerPermission::can($student, 'view_calendar'));
    }

    public function test_can_denies_permission_for_an_officer_role_account_whose_position_was_deactivated(): void
    {
        $user = $this->makeOfficer('Public Relations Officer', ['view_calendar'], isActive: false);

        $this->assertFalse(OfficerPermission::can($user, 'view_calendar'));
    }

    /**
     * Access now comes only from admin's checked boxes on the officer's
     * active position — not from position title/tier.
     */
    public function test_can_grants_only_the_permissions_admin_checked(): void
    {
        $officer = $this->makeOfficer('Secretary', ['manage_attendance', 'view_reports']);

        $this->assertTrue(OfficerPermission::can($officer, 'manage_attendance'));
        $this->assertTrue(OfficerPermission::can($officer, 'view_reports'));
        $this->assertFalse(OfficerPermission::can($officer, 'manage_announcements'));
    }

    public function test_can_denies_a_permission_no_officer_has_been_granted(): void
    {
        $officer = $this->makeOfficer('President', []);

        $this->assertFalse(OfficerPermission::can($officer, 'manage_events'));
    }

    public function test_is_treasurer_is_true_only_for_the_active_treasurer(): void
    {
        $treasurer = $this->makeOfficer('Treasurer');
        $secretary = $this->makeOfficer('Secretary');

        $this->assertTrue(OfficerPermission::isTreasurer($treasurer));
        $this->assertFalse(OfficerPermission::isTreasurer($secretary));
    }
}
