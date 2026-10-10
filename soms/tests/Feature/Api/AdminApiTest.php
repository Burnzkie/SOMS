<?php
// tests/Feature/Api/AdminApiTest.php

namespace Tests\Feature\Api;

use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the mobile-facing Api\Admin\UserController and
 * Api\Admin\OfficerAppointmentController — the JSON mirror of the web
 * Admin\UserController / Admin\OfficerAppointmentController tests that
 * already exist (OfficerRevocationTest). Nothing here exercised the
 * /api/v1/admin/* routes before this batch.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['*']);
        return $admin;
    }

    // -----------------------------------------------------------------
    // UserController::approve / reject
    // -----------------------------------------------------------------

    public function test_admin_can_approve_a_pending_student_via_api(): void
    {
        $this->actingAsAdmin();
        Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $student = User::factory()->unapproved()->create();

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/approve");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertTrue($student->fresh()->is_approved);
        $this->assertDatabaseHas('organization_members', ['user_id' => $student->id]);
    }

    public function test_approving_an_already_approved_student_is_rejected(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->create(); // is_approved true by default

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/approve");

        $response->assertStatus(422);
    }

    public function test_admin_can_reject_a_pending_student_via_api(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->unapproved()->create();

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/reject", [
            'reason' => 'Student ID photo does not match records.',
        ]);

        $response->assertOk();
        // User uses SoftDeletes -- delete() sets deleted_at, the row
        // itself stays in the table, so assertDatabaseMissing (which
        // checks the raw row) was the wrong assertion here.
        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_rejecting_without_a_reason_is_rejected(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->unapproved()->create();

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/reject", []);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    // -----------------------------------------------------------------
    // OfficerAppointmentController::revoke
    // -----------------------------------------------------------------

    public function test_admin_can_revoke_an_officer_via_api(): void
    {
        $this->actingAsAdmin();
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
            'position_title' => 'Secretary',
            'permissions' => [],
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/admin/officers/{$position->id}/revoke");

        $response->assertOk();
        $this->assertFalse($position->fresh()->is_active);
        $this->assertSame('student', $officer->fresh()->role);
    }

    // -----------------------------------------------------------------
    // Role boundary — the /admin prefix group is role:admin only
    // -----------------------------------------------------------------

    public function test_a_student_cannot_reach_the_admin_api_at_all(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['*']);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertForbidden();
    }

    public function test_an_officer_cannot_reach_the_admin_api_either(): void
    {
        // A nonexistent {position} id 404s via route-model-binding before
        // role:admin even runs -- that would test the wrong thing (a
        // missing row, not the role boundary). Use a real one so the
        // request actually reaches the role check.
        $org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $targetOfficer = User::factory()->officer()->create();
        $position = OfficerPosition::create([
            'user_id' => $targetOfficer->id,
            'organization_id' => $org->id,
            'position_title' => 'Auditor',
            'permissions' => [],
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        $officer = User::factory()->officer()->create();
        Sanctum::actingAs($officer, ['*']);

        $response = $this->postJson("/api/v1/admin/officers/{$position->id}/revoke");

        $response->assertForbidden();
    }
}
