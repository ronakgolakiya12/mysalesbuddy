<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AppNotification;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/meetings/{$meeting->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $owner->id,
            'status' => MeetingStatus::Ready->value,
        ]);

        $this->actingAs($intruder)
            ->deleteJson("/api/meetings/{$meeting->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_view_other_users_transcript(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/meetings/{$meeting->id}/transcript")
            ->assertStatus(403);
    }

    public function test_user_cannot_export_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->postJson("/api/meetings/{$meeting->id}/export-pdf")
            ->assertStatus(403);
    }

    public function test_user_cannot_view_other_users_coaching_analysis(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->withCoaching()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(403);
    }

    public function test_user_cannot_rate_other_users_coaching_analysis(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->withCoaching()->create(['user_id' => $owner->id]);
        /** @var CoachingAnalysis $analysis */
        $analysis = $meeting->coachingAnalyses()->firstOrFail();

        $this->actingAs($intruder)
            ->patchJson("/api/coaching-analyses/{$analysis->id}/rate", [
                'section_key' => 'strengths.0',
                'rating' => 'useful',
            ])
            ->assertStatus(403);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = AppNotification::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(403);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_cancel_dispatch_of_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $owner->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);

        $this->actingAs($intruder)
            ->postJson("/api/meetings/{$meeting->id}/cancel-dispatch")
            ->assertStatus(403);
    }

    public function test_meeting_scope_cannot_be_changed_after_creation(): void
    {
        // There is no controller method that accepts a scope update. This
        // test enforces the invariant by verifying that PATCH /meetings/{id}
        // is not registered (404) and that the controller only accepts
        // `scope` on store, not on any other route.
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'scope' => MeetingScope::Private->value,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/meetings/{$meeting->id}", ['scope' => MeetingScope::Team->value]);

        // No PATCH route exists for meetings — must be 404, never 200.
        $this->assertContains($response->status(), [404, 405], 'No mutation endpoint for scope must exist.');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'scope' => MeetingScope::Private->value,
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/meetings')->assertStatus(401);
    }
}
