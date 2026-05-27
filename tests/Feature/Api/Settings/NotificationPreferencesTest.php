<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_defaults_when_unset(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->actingAs($user);
        $response = $this->getJson('/api/settings/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('data.preferences.pdf_ready.in_app', true);
        $response->assertJsonPath('data.preferences.pdf_ready.email', true);
        $response->assertJsonPath('data.preferences.coaching_ready.email', false);
    }

    public function test_update_persists_filtered_preferences(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/settings/notifications', [
            'preferences' => [
                'pdf_ready' => ['in_app' => true, 'email' => false],
                'unknown_key' => ['in_app' => true, 'email' => true],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.preferences.pdf_ready.email', false);

        $stored = $user->fresh()->notification_preferences;
        $this->assertArrayNotHasKey('unknown_key', $stored);
        $this->assertSame(false, $stored['pdf_ready']['email']);
    }

    public function test_update_validates_shape(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->patchJson('/api/settings/notifications', [
            'preferences' => [
                'pdf_ready' => ['email' => 'not-a-bool'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['preferences.pdf_ready.email']);
    }
}
