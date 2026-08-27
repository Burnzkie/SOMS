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

    protected function makeOfficer(string $positionTitle): User
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
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_tier_resolves_correctly_for_each_officer_position(): void
    {
        $this->assertSame('Executive', OfficerPermission::tier($this->makeOfficer('President')));
        $this->assertSame('Executive', OfficerPermission::tier($this->makeOfficer('Vice President')));
        $this->assertSame('Administrative', OfficerPermission::tier($this->makeOfficer('Secretary')));
        $this->assertSame('Administrative', OfficerPermission::tier($this->makeOfficer('Treasurer')));
        $this->assertSame('Administrative', OfficerPermission::tier($this->makeOfficer('Auditor')));
        $this->assertSame('PublicRelations', OfficerPermission::tier($this->makeOfficer('Public Relations Officer')));
    }

    /**
     * Direct regression test for the bug fixed in this project: tier()
     * used to fall through to the string 'PublicRelations' for any user
     * with no active officer position at all, silently granting
     * draft_announcements/view_dashboard/view_calendar to plain students
     * and revoked officers. It must return null instead.
     */
    public function test_tier_returns_null_for_a_plain_student_with_no_officer_position(): void
    {
        $student = User::factory()->create();

        $this->assertNull(OfficerPermission::tier($student));
    }

    public function test_tier_returns_null_for_an_officer_role_account_whose_position_was_deactivated(): void
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
            'position_title' => 'Public Relations Officer',
            'academic_year' => '2026-2027',
            'is_active' => false, // revoked / term ended
            'appointed_at' => now()->subMonths(6),
        ]);

        $this->assertNull(OfficerPermission::tier($user->fresh()));
    }

    /**
     * The practical consequence of the bug above: can() must deny a
     * PublicRelations-tier permission for a user with no tier, not
     * silently grant it.
     */
    public function test_can_denies_permission_for_a_user_with_no_active_position(): void
    {
        $student = User::factory()->create();

        $this->assertFalse(OfficerPermission::can($student, 'draft_announcements'));
        $this->assertFalse(OfficerPermission::can($student, 'view_dashboard'));
        $this->assertFalse(OfficerPermission::can($student, 'view_calendar'));
    }

    public function test_can_grants_permission_matching_the_officers_tier(): void
    {
        $treasurer = $this->makeOfficer('Treasurer');

        $this->assertTrue(OfficerPermission::can($treasurer, 'manage_attendance'));
        $this->assertTrue(OfficerPermission::can($treasurer, 'view_reports'));
        // Administrative tier does not include manage_announcements (Executive only).
        $this->assertFalse(OfficerPermission::can($treasurer, 'manage_announcements'));
    }

    public function test_is_treasurer_is_true_only_for_the_active_treasurer(): void
    {
        $treasurer = $this->makeOfficer('Treasurer');
        $secretary = $this->makeOfficer('Secretary');

        $this->assertTrue(OfficerPermission::isTreasurer($treasurer));
        $this->assertFalse(OfficerPermission::isTreasurer($secretary));
    }
}
