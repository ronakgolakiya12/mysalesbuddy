<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Settings;

use App\Models\CoachingPromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_user_versions_latest_first(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $old = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
            'created_at' => now()->subDay(),
        ]);
        $active = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now(),
        ]);
        CoachingPromptVersion::factory()->create([
            'user_id' => $other->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/settings/prompt')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.1.id', $old->id);
    }

    public function test_store_creates_new_active_version_and_logs_audit(): void
    {
        $user = User::factory()->create();
        $existing = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/prompt', [
                'prompt_text' => str_repeat('A brand new coaching prompt body with plenty of guidance text. ', 5),
            ])
            ->assertStatus(201);

        $newId = $response->json('data.id');
        $this->assertNotNull($newId);

        $this->assertDatabaseHas('coaching_prompt_versions', [
            'id' => $newId,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('coaching_prompt_versions', [
            'id' => $existing->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'prompt.version_created',
            'entity_id' => $newId,
        ]);
    }

    public function test_store_validates_min_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/settings/prompt', [
                'prompt_text' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt_text']);
    }

    public function test_restore_creates_new_active_copy(): void
    {
        $user = User::factory()->create();
        $original = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'prompt_text' => 'Original prompt body content.',
            'is_active' => false,
            'created_at' => now()->subDay(),
        ]);
        CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'prompt_text' => 'Current prompt body content.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/settings/prompt/{$original->id}/restore")
            ->assertStatus(201);

        $newId = $response->json('data.id');
        $this->assertNotSame($original->id, $newId);
        $this->assertSame('Original prompt body content.', $response->json('data.prompt_text'));
        $this->assertTrue((bool) $response->json('data.is_active'));

        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'prompt.version_created',
            'entity_id' => $newId,
        ]);
    }

    public function test_restore_returns_403_for_other_users_version(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $version = CoachingPromptVersion::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->postJson("/api/settings/prompt/{$version->id}/restore")
            ->assertStatus(403);
    }
}
