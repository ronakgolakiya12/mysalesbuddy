<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Settings;

use App\Models\NotetakerConfig;
use App\Models\User;
use App\Support\Enums\MeetingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotetakerConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_config_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        NotetakerConfig::factory()->create([
            'user_id' => $user->id,
            'display_name' => "Alice's Assistant",
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/settings/notetaker');

        $response->assertStatus(200);
        $response->assertJsonPath('data.display_name', "Alice's Assistant");
    }

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();
        NotetakerConfig::factory()->create([
            'user_id' => $user->id,
            'display_name' => 'Old Name',
            'default_scope' => MeetingScope::Private->value,
        ]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/settings/notetaker', [
            'display_name' => 'New Name',
            'intro_message' => 'Hello',
            'default_scope' => 'team',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.display_name', 'New Name');
        $this->assertDatabaseHas('notetaker_configs', [
            'user_id' => $user->id,
            'display_name' => 'New Name',
            'intro_message' => 'Hello',
            'default_scope' => 'team',
        ]);
    }

    public function test_update_validates_scope(): void
    {
        $user = User::factory()->create();
        NotetakerConfig::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/settings/notetaker', [
            'default_scope' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['default_scope']);
    }

    public function test_upload_avatar_stores_file_and_returns_signed_url(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        NotetakerConfig::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        $response = $this->postJson('/api/settings/notetaker/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 100, 100),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['avatar_url']]);

        $config = NotetakerConfig::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($config->avatar_path);
        Storage::disk('s3')->assertExists($config->avatar_path);
    }

    public function test_upload_avatar_rejects_invalid_mime(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        NotetakerConfig::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        $response = $this->postJson('/api/settings/notetaker/avatar', [
            'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }
}
