<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\Enums\CoachingMode;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function seedTranscript(Meeting $meeting): void
    {
        TranscriptSegment::insert([
            [
                'id' => (string) Str::uuid(),
                'meeting_id' => $meeting->id,
                'speaker_label' => 'Rep',
                'body' => 'Welcome to the call.',
                'start_ms' => 0,
                'end_ms' => 2000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'meeting_id' => $meeting->id,
                'speaker_label' => 'Prospect',
                'body' => 'Thanks for having me.',
                'start_ms' => 2200,
                'end_ms' => 3500,
                'created_at' => now(),
            ],
        ]);

        CoachingAnalysis::create([
            'meeting_id' => $meeting->id,
            'mode' => CoachingMode::TranscriptOnly,
            'talk_time_rep' => 60,
            'talk_time_prospect' => 40,
            'triggered_by' => 'auto',
        ]);
    }

    public function test_transcript_endpoint_returns_segments(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $this->seedTranscript($meeting);

        $response = $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'segments' => [['id', 'speaker_label', 'body', 'start_ms', 'end_ms']],
                    'talk_time_rep',
                    'talk_time_prospect',
                ],
            ])
            ->assertJsonPath('data.talk_time_rep', 60)
            ->assertJsonPath('data.talk_time_prospect', 40)
            ->assertJsonCount(2, 'data.segments');
    }

    public function test_transcript_returns_409_when_not_ready(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->state([
            'status' => MeetingStatus::Processing->value,
        ])->create();

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript")
            ->assertStatus(409);
    }

    public function test_transcript_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meeting = Meeting::factory()->for($owner)->ready()->create();

        $this->actingAs($other)
            ->getJson("/api/meetings/{$meeting->id}/transcript")
            ->assertStatus(403);
    }
}
