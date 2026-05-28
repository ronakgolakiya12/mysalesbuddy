<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\CoachingAnalysisJob;
use App\Models\CoachingAnalysis;
use App\Models\CoachingPromptVersion;
use App\Models\CoachingRating;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Enums\CoachingMode;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CoachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_latest_analysis_for_owner(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        CoachingAnalysis::factory()->completed()->create([
            'meeting_id' => $meeting->id,
            'created_at' => now()->subHour(),
        ]);
        $newest = CoachingAnalysis::factory()->completed()->create([
            'meeting_id' => $meeting->id,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $newest->id)
            ->assertJsonPath('data.overall_score', 8)
            ->assertJsonPath('data.output_json.one_liner', 'Strong discovery with a clearly committed next step.');
    }

    public function test_show_returns_404_when_no_analysis(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(404);
    }

    public function test_show_returns_403_for_other_users_meeting(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(403);
    }

    public function test_trigger_queues_job_and_logs_audit(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/coaching/trigger", [
                'mode' => CoachingMode::DiscoveryAware->value,
                'deal_context' => 'ACME — $50k deal.',
            ])
            ->assertStatus(202);

        $analysisId = $response->json('data.id');
        $this->assertNotNull($analysisId);

        Queue::assertPushed(CoachingAnalysisJob::class, function (CoachingAnalysisJob $job) use ($meeting, $analysisId): bool {
            return $job->meeting->id === $meeting->id
                && $job->analysisId === $analysisId
                && $job->mode === CoachingMode::DiscoveryAware->value
                && $job->dealContext === 'ACME — $50k deal.';
        });

        $this->assertDatabaseHas('coaching_analyses', [
            'id' => $analysisId,
            'meeting_id' => $meeting->id,
            'mode' => CoachingMode::DiscoveryAware->value,
            'triggered_by' => 'manual',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'coaching.triggered',
            'entity_id' => $analysisId,
        ]);
    }

    public function test_trigger_returns_409_when_meeting_not_ready(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'status' => MeetingStatus::Recording->value,
        ]);

        $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/coaching/trigger", [
                'mode' => CoachingMode::TranscriptOnly->value,
            ])
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_trigger_returns_403_for_other_users_meeting(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->postJson("/api/meetings/{$meeting->id}/coaching/trigger", [
                'mode' => CoachingMode::TranscriptOnly->value,
            ])
            ->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_rate_item_creates_or_updates_rating(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        $analysis = CoachingAnalysis::factory()->completed()->create(['meeting_id' => $meeting->id]);

        $this->actingAs($user)
            ->patchJson("/api/coaching-analyses/{$analysis->id}/rate", [
                'section_key' => 'strengths',
                'rating' => 'useful',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.section_key', 'strengths')
            ->assertJsonPath('data.rating', 'useful');

        $this->assertDatabaseHas('coaching_ratings', [
            'coaching_analysis_id' => $analysis->id,
            'section_key' => 'strengths',
            'rating' => 'useful',
        ]);

        // Update same section key.
        $this->actingAs($user)
            ->patchJson("/api/coaching-analyses/{$analysis->id}/rate", [
                'section_key' => 'strengths',
                'rating' => 'not_useful',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.rating', 'not_useful');

        $this->assertSame(1, CoachingRating::where('coaching_analysis_id', $analysis->id)->count());
        $this->assertDatabaseHas('coaching_ratings', [
            'coaching_analysis_id' => $analysis->id,
            'section_key' => 'strengths',
            'rating' => 'not_useful',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'coaching.rated',
        ]);
    }

    public function test_rate_item_returns_403_for_other_users_analysis(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $owner->id]);
        $analysis = CoachingAnalysis::factory()->completed()->create(['meeting_id' => $meeting->id]);

        $this->actingAs($intruder)
            ->patchJson("/api/coaching-analyses/{$analysis->id}/rate", [
                'section_key' => 'strengths',
                'rating' => 'useful',
            ])
            ->assertStatus(403);
    }

    public function test_trigger_validates_mode(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        // Ensure prompt exists in case validation passes accidentally
        CoachingPromptVersion::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/meetings/{$meeting->id}/coaching/trigger", [
                'mode' => 'invalid_mode',
            ])
            ->assertStatus(422);
    }
}
