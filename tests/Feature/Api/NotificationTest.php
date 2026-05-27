<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_unread_notifications_for_user(): void
    {
        $user = User::factory()->create();
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => ['meeting_id' => 'abc'],
            'read_at' => null,
        ]);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'transcript_failed',
            'payload_json' => [],
            'read_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'pdf_ready');
    }

    public function test_mark_read_updates_read_at(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);

        $this->actingAs($user);
        $response = $this->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(204);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_rejects_other_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = AppNotification::query()->create([
            'user_id' => $owner->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);

        $this->actingAs($other);
        $response = $this->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_updates_all_user_notifications(): void
    {
        $user = User::factory()->create();
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'transcript_failed',
            'payload_json' => [],
            'read_at' => null,
        ]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJsonPath('data.updated', 2);
        $this->assertSame(0, $user->notifications()->whereNull('read_at')->count());
    }
}
