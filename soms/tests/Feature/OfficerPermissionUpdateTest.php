<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OfficerPosition;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerPermissionUpdateTest extends TestCase
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

    public function test_admin_can_grant_permissions_via_checkboxes(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer('Secretary', []);

        $response = $this->actingAs($admin)->put(
            route('admin.permissions.update', $position),
            ['permissions' => ['manage_events', 'view_calendar']]
        );

        $response->assertRedirect();
        $this->assertTrue(OfficerPermission::can($officer->fresh(), 'manage_events'));
        $this->assertTrue(OfficerPermission::can($officer->fresh(), 'view_calendar'));
        $this->assertFalse(OfficerPermission::can($officer->fresh(), 'manage_members'));
    }

    public function test_admin_can_revoke_a_previously_granted_permission_by_unchecking_it(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, $position] = $this->makeActiveOfficer('Secretary', ['manage_events', 'manage_members']);

        $this->actingAs($admin)->put(
            route('admin.permissions.update', $position),
            ['permissions' => ['manage_events']]
        );

        $this->assertTrue(OfficerPermission::can($officer->fresh(), 'manage_events'));
        $this->assertFalse(OfficerPermission::can($officer->fresh(), 'manage_members'));
    }

    public function test_an_unknown_permission_key_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [, $position] = $this->makeActiveOfficer('Secretary', []);

        $response = $this->actingAs($admin)->put(
            route('admin.permissions.update', $position),
            ['permissions' => ['not_a_real_permission']]
        );

        $response->assertSessionHasErrors('permissions.0');
    }

    public function test_a_regular_student_cannot_update_officer_permissions(): void
    {
        $student = User::factory()->create();
        [, $position] = $this->makeActiveOfficer('Secretary', []);

        $response = $this->actingAs($student)->put(
            route('admin.permissions.update', $position),
            ['permissions' => ['manage_events']]
        );

        $response->assertForbidden();
    }

    public function test_permissions_index_lists_every_active_officer(): void
    {
        $admin = User::factory()->admin()->create();
        [$officer, ] = $this->makeActiveOfficer('Treasurer', ['manage_events']);

        $response = $this->actingAs($admin)->get(route('admin.permissions.index'));

        $response->assertOk();
        $response->assertSee($officer->name);
        $response->assertSee('Treasurer');
    }

    public function test_permissions_edit_page_pre_checks_the_officers_granted_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        [, $position] = $this->makeActiveOfficer('Secretary', ['manage_events']);

        $response = $this->actingAs($admin)->get(route('admin.permissions.edit', $position));

        $response->assertOk();
        $response->assertSee('checked', false);
    }

    public function test_a_regular_student_cannot_view_the_permissions_pages(): void
    {
        $student = User::factory()->create();
        [, $position] = $this->makeActiveOfficer('Secretary', []);

        $this->actingAs($student)->get(route('admin.permissions.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.permissions.edit', $position))->assertForbidden();
    }
}
