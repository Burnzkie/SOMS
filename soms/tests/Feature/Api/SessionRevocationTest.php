<?php
// tests/Feature/Api/SessionRevocationTest.php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers Api\Auth\SessionController — added to close the gap where a
 * Sanctum token (default 30-day expiration, config/sanctum.php) had no
 * way to be listed or revoked by the user before it expired on its own,
 * e.g. after a lost/stolen phone.
 *
 * Deliberately uses real `createToken()` + Bearer-header auth rather
 * than the Sanctum::actingAs() test helper: actingAs() issues an
 * in-memory TransientToken that is never written to
 * personal_access_tokens, so it has no real numeric ID to assert
 * against. SessionController's whole job is managing real token IDs,
 * so the tests need to authenticate the same way the Flutter app
 * actually does.
 */
class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function bearer(User $user, string $tokenName = 'current-device'): array
    {
        $plainTextToken = $user->createToken($tokenName)->plainTextToken;
        return ['Authorization' => "Bearer {$plainTextToken}"];
    }

    public function test_user_can_list_their_own_active_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('old-device');
        $headers = $this->bearer($user, 'current-device');

        $response = $this->withHeaders($headers)->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $this->assertCount(2, $response->json('data')); // old-device + current-device
    }

    public function test_the_authenticating_token_is_flagged_as_current_in_the_list(): void
    {
        $user = User::factory()->create();
        $user->createToken('old-device');
        $headers = $this->bearer($user, 'current-device');

        $response = $this->withHeaders($headers)->getJson('/api/v1/auth/sessions');

        $current = collect($response->json('data'))->firstWhere('is_current', true);
        $this->assertNotNull($current);
        $this->assertSame('current-device', $current['name']);
    }

    public function test_user_can_revoke_a_specific_other_session_by_id(): void
    {
        $user = User::factory()->create();
        $otherToken = $user->createToken('other-device');
        $headers = $this->bearer($user, 'current-device');

        $response = $this->withHeaders($headers)->deleteJson("/api/v1/auth/sessions/{$otherToken->accessToken->id}");

        $response->assertOk();
        $response->assertJson(['data' => ['was_current' => false]]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_revoking_the_current_session_is_allowed_and_flagged_as_such(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('current-device')->plainTextToken;
        $currentTokenId = $user->tokens()->latest('id')->first()->id;

        $response = $this->withHeaders(['Authorization' => "Bearer {$plainTextToken}"])
            ->deleteJson("/api/v1/auth/sessions/{$currentTokenId}");

        $response->assertOk();
        $response->assertJson(['data' => ['was_current' => true]]);
    }

    /**
     * The core authorization guarantee: a token ID is scoped through
     * $user->tokens() rather than a global lookup, so a user can never
     * revoke — or even detect the existence of — someone else's token.
     */
    public function test_a_user_cannot_revoke_another_users_token(): void
    {
        $victim = User::factory()->create();
        $victimToken = $victim->createToken('victim-device');

        $attacker = User::factory()->create();
        $headers = $this->bearer($attacker, 'attacker-device');

        $response = $this->withHeaders($headers)->deleteJson("/api/v1/auth/sessions/{$victimToken->accessToken->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $victimToken->accessToken->id]);
    }

    public function test_revoke_others_keeps_current_session_and_kills_every_other_one(): void
    {
        $user = User::factory()->create();
        $user->createToken('device-a');
        $user->createToken('device-b');
        $plainTextToken = $user->createToken('current-device')->plainTextToken;
        $currentTokenId = $user->tokens()->latest('id')->first()->id;

        $response = $this->withHeaders(['Authorization' => "Bearer {$plainTextToken}"])
            ->postJson('/api/v1/auth/sessions/revoke-others');

        $response->assertOk();
        // 3 tokens exist total (device-a, device-b, current-device);
        // revoke-others kills everything except current -- 2, not 3.
        $response->assertJson(['data' => ['revoked_count' => 2]]);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_revoking_an_unknown_session_id_returns_404(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user, 'current-device');

        $response = $this->withHeaders($headers)->deleteJson('/api/v1/auth/sessions/999999');

        $response->assertStatus(404);
    }
}
