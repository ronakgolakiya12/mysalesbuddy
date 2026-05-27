<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\DispatchBotJob;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_meetings_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Meeting::factory()->count(2)->create(['user_id' => $user->id]);
        Meeting::factory()->count(3)->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->getJson('/api/meetings')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_supports_status_search_and_date_filters(): void
    {
        $user = User::factory()->create();
        Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Ready->value, 'title' => 'Discovery with Acme']);
        Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Failed->value, 'title' => 'Closing call']);

        $this->actingAs($user)
            ->getJson('/api/meetings?status=ready')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'ready');

        $this->actingAs($user)
            ->getJson('/api/meetings?search=Acme')
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_full_meeting_with_transcript_relation(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $meeting->id)
            ->assertJsonMissing(['recall_bot_id' => $meeting->recall_bot_id])
            ->assertJsonStructure(['data' => ['id', 'status', 'transcript_segments']]);
    }

    public function test_show_returns_403_for_other_users_meeting(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}")
            ->assertStatus(403);
    }

    public function test_store_creates_meeting_and_dispatches_job_when_no_scheduled_at(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/meetings', [
            'external_meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'title' => 'Demo with Globex',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Demo with Globex');

        $this->assertDatabaseHas('meetings', [
            'user_id' => $user->id,
            'external_meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'status' => MeetingStatus::Scheduled->value,
        ]);

        Queue::assertPushed(DispatchBotJob::class);
    }

    public function test_store_rejects_non_google_meet_urls(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/meetings', [
            'external_meeting_url' => 'https://zoom.us/j/12345',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['external_meeting_url']);

        Queue::assertNothingPushed();
    }

    public function test_store_does_not_dispatch_job_when_scheduled_in_future(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/meetings', [
            'external_meeting_url' => 'https://meet.google.com/sch-edul-ed1',
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertStatus(201);
        Queue::assertNotPushed(DispatchBotJob::class);
    }

    public function test_destroy_blocks_deletion_while_bot_is_active(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->recording()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/meetings/{$meeting->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cancel_dispatch_works_within_thirty_seconds(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);

        $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/cancel-dispatch")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/cancel-dispatch")
            ->assertStatus(422);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/meetings')->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $meeting = Meeting::factory()->create();
        $this->getJson("/api/meetings/{$meeting->id}")->assertStatus(401);
    }

    public function test_index_filters_by_from_date(): void
    {
        $user = User::factory()->create();
        $old = Meeting::factory()->create(['user_id' => $user->id]);
        $old->created_at = now()->subDays(10);
        $old->save();

        $recent = Meeting::factory()->create(['user_id' => $user->id]);

        $from = now()->subDays(3)->toDateString();
        $response = $this->actingAs($user)
            ->getJson('/api/meetings?from='.$from);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $recent->id);
    }

    public function test_index_filters_by_to_date(): void
    {
        $user = User::factory()->create();
        $old = Meeting::factory()->create(['user_id' => $user->id]);
        $old->created_at = now()->subDays(10);
        $old->save();

        $recent = Meeting::factory()->create(['user_id' => $user->id]);
        $recent->created_at = now();
        $recent->save();

        $to = now()->subDays(3)->toDateString();
        $response = $this->actingAs($user)
            ->getJson('/api/meetings?to='.$to);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $old->id);
    }

    public function test_index_paginates_results(): void
    {
        $user = User::factory()->create();
        Meeting::factory()->count(25)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/meetings');

        $response->assertStatus(200);
        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_store_validates_required_url(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/meetings', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['external_meeting_url']);
    }

    public function test_store_accepts_optional_title(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/meetings', [
            'external_meeting_url' => 'https://meet.google.com/aaa-bbbb-ccc',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', null);
    }

    public function test_destroy_soft_deletes_a_terminal_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/meetings/{$meeting->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }

    public function test_cancel_dispatch_blocked_after_thirty_seconds(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);
        $meeting->created_at = now()->subSeconds(60);
        $meeting->save();

        $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/cancel-dispatch")
            ->assertStatus(422);
    }

    public function test_destroy_returns_403_for_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->deleteJson("/api/meetings/{$meeting->id}")
            ->assertStatus(403);
    }
}
