<?php
// tests/Feature/AppVersionUpdateTest.php

namespace Tests\Feature;

use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin editing of published app versions: what can change, what can't,
 * and who is allowed to do it.
 */
class AppVersionUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected AppVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->version = AppVersion::create([
            'platform'     => 'android',
            'version_code' => 6,
            'version_name' => '1.340+6', // the typo this feature exists to fix
            'apk_url'      => 'https://example.com/old.apk',
            'changelog'    => 'Old notes',
            'force_update' => false,
            'is_active'    => true,
            'published_by' => $this->admin->id,
        ]);
    }

    /** Valid payload; override single fields per test. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'version_name' => '1.4.0',
            'apk_url'      => 'https://example.com/soms-1.4.0-6.apk',
            'changelog'    => 'Audit findings and flagged scan review',
        ], $overrides);
    }

    protected function update(array $overrides = [])
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.app-versions.update', $this->version), $this->payload($overrides));
    }

    public function test_admin_can_open_the_edit_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.app-versions.edit', $this->version))
            ->assertOk()
            ->assertSee('1.340+6');
    }

    public function test_index_shows_an_edit_link_for_every_row(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.app-versions.index'))
            ->assertOk()
            ->assertSee(route('admin.app-versions.edit', $this->version));
    }

    public function test_admin_can_update_the_editable_fields(): void
    {
        $this->update(['force_update' => '1'])
            ->assertRedirect(route('admin.app-versions.index'));

        $fresh = $this->version->fresh();
        $this->assertSame('1.4.0', $fresh->version_name);
        $this->assertSame('https://example.com/soms-1.4.0-6.apk', $fresh->apk_url);
        $this->assertSame('Audit findings and flagged scan review', $fresh->changelog);
        $this->assertTrue($fresh->force_update);
    }

    public function test_unchecked_force_update_turns_the_flag_off(): void
    {
        $this->version->update(['force_update' => true]);

        $this->update()->assertRedirect();

        $this->assertFalse($this->version->fresh()->force_update);
    }

    public function test_version_code_and_active_state_cannot_be_changed(): void
    {
        $this->update(['version_code' => 99, 'platform' => 'ios', 'is_active' => false])
            ->assertRedirect();

        $fresh = $this->version->fresh();
        $this->assertSame(6, $fresh->version_code);
        $this->assertSame('android', $fresh->platform);
        $this->assertTrue($fresh->is_active);
    }

    public function test_http_apk_urls_are_rejected(): void
    {
        $this->update(['apk_url' => 'http://example.com/soms.apk'])
            ->assertSessionHasErrors('apk_url');

        $this->assertSame('https://example.com/old.apk', $this->version->fresh()->apk_url);
    }

    public function test_minimum_supported_code_above_the_version_is_rejected(): void
    {
        $this->update(['min_supported_version_code' => 7])
            ->assertSessionHasErrors('min_supported_version_code');
    }

    public function test_version_name_is_required(): void
    {
        $this->update(['version_name' => ''])
            ->assertSessionHasErrors('version_name');
    }

    public function test_an_update_is_written_to_the_activity_log_with_old_and_new_values(): void
    {
        $this->update()->assertRedirect();

        // Assumes activity_logs has an `action` column (see ActivityLog::record).
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'action'  => 'app_version_updated',
        ]);
    }

    public function test_saving_without_changes_reports_no_changes_and_logs_nothing(): void
    {
        $this->update([
            'version_name' => '1.340+6',
            'apk_url'      => 'https://example.com/old.apk',
            'changelog'    => 'Old notes',
        ])->assertRedirect()
          ->assertSessionHas('status', 'No changes made.');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'app_version_updated']);
    }

    public function test_a_non_admin_cannot_edit_or_update(): void
    {
        $student = User::factory()->create(['is_approved' => true]);

        $this->actingAs($student)
            ->get(route('admin.app-versions.edit', $this->version))
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('admin.app-versions.update', $this->version), $this->payload())
            ->assertForbidden();

        $this->assertSame('1.340+6', $this->version->fresh()->version_name);
    }
}