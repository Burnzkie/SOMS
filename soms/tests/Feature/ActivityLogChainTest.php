<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_verifies_clean_immediately_after_writing(): void
    {
        $user = User::factory()->create();

        ActivityLog::record($user->id, 'login', User::class, $user->id, ['ip' => '203.0.113.5']);
        ActivityLog::record($user->id, 'update_profile', User::class, $user->id, ['field' => 'name']);
        ActivityLog::record($user->id, 'logout');

        // This is the regression check: previously `created_at` used in the
        // write-time hash was never the value actually persisted, so this
        // always came back false even with zero tampering.
        $this->assertTrue(ActivityLog::verifyChainIntegrity());
    }

    public function test_payload_round_trips_as_an_array_not_a_json_string(): void
    {
        $user = User::factory()->create();

        $log = ActivityLog::record($user->id, 'issue_fine', null, null, ['amount' => 50, 'reason' => 'late']);

        $fresh = ActivityLog::find($log->id);

        $this->assertIsArray($fresh->payload);
        $this->assertSame(['amount' => 50, 'reason' => 'late'], $fresh->payload);
    }

    public function test_tampering_with_a_stored_entry_is_detected(): void
    {
        $user = User::factory()->create();

        ActivityLog::record($user->id, 'login');
        $log = ActivityLog::record($user->id, 'update_profile', null, null, ['field' => 'email']);
        ActivityLog::record($user->id, 'logout');

        $log->forceFill(['action' => 'delete_account'])->saveQuietly();

        $this->assertFalse(ActivityLog::verifyChainIntegrity());
    }

    public function test_ip_address_display_is_masked_but_full_ip_is_retained(): void
    {
        $user = User::factory()->create();

        $log = ActivityLog::record($user->id, 'login', null, null, null, '203.0.113.42');

        $this->assertSame('203.0.113.42', $log->ip_address);
        $this->assertSame('203.0.113.***', $log->ip_address_display);
    }
}
