<?php
// tests/Feature/Api/AvatarUploadTest.php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers Api\AvatarController — the mobile counterpart of the web
 * AvatarController, same SafeImageUpload pipeline and UserPolicy
 * self-only rule. Nothing exercised this path before this batch.
 */
class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_user_can_upload_their_first_avatar(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $response->assertCreated();
        $response->assertJson(['success' => true]);
        $this->assertNotNull($user->fresh()->avatar_path);
    }

    public function test_uploading_a_second_avatar_via_store_is_rejected_with_409(): void
    {
        $user = User::factory()->create(['avatar_path' => 'avatars/existing.jpg']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $response->assertStatus(409);
    }

    public function test_user_can_replace_an_existing_avatar_via_update(): void
    {
        Storage::disk('r2')->put('avatars/old.jpg', 'old-bytes');
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson('/api/v1/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotSame('avatars/old.jpg', $user->fresh()->avatar_path);
        Storage::disk('r2')->assertMissing('avatars/old.jpg');
    }

    public function test_updating_with_no_existing_avatar_returns_404(): void
    {
        $user = User::factory()->create(); // no avatar_path
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson('/api/v1/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertStatus(404);
    }

    public function test_user_can_delete_their_own_avatar(): void
    {
        Storage::disk('r2')->put('avatars/mine.jpg', 'bytes');
        $user = User::factory()->create(['avatar_path' => 'avatars/mine.jpg']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson('/api/v1/avatar');

        $response->assertOk();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('r2')->assertMissing('avatars/mine.jpg');
    }

    public function test_a_non_image_file_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/avatar', [
            'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 100),
        ]);

        $response->assertStatus(422);
    }
}
