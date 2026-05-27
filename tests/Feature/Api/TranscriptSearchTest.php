<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TranscriptSearchTest extends TestCase
{
    use RefreshDatabase;

    private function seedSegments(Meeting $meeting, array $bodies): void
    {
        $rows = [];
        $offset = 0;
        foreach ($bodies as $body) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'meeting_id' => $meeting->id,
                'speaker_label' => 'Rep',
                'body' => $body,
                'start_ms' => $offset,
                'end_ms' => $offset + 2000,
                'created_at' => now(),
            ];
            $offset += 3000;
        }
        TranscriptSegment::insert($rows);
    }

    public function test_search_filters_segments_by_keyword(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $this->seedSegments($meeting, [
            'We should discuss pricing today',
            'Let me follow up next week',
            'The pricing model looks good',
        ]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript?search=pricing")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.segments')
            ->assertJsonPath('data.match_count', 2)
            ->assertJsonPath('data.search', 'pricing');
    }

    public function test_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $this->seedSegments($meeting, ['Discuss PRICING today']);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript?search=pricing")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.segments');
    }

    public function test_search_returns_all_when_query_blank(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $this->seedSegments($meeting, ['One', 'Two', 'Three']);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript")
            ->assertStatus(200)
            ->assertJsonPath('data.match_count', null)
            ->assertJsonCount(3, 'data.segments');
    }

    public function test_search_returns_total_segments_count(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $this->seedSegments($meeting, [
            'Talking about pricing strategy',
            'Random other content',
            'More other content',
        ]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript?search=pricing")
            ->assertStatus(200)
            ->assertJsonPath('data.total_segments', 3)
            ->assertJsonPath('data.match_count', 1);
    }

    public function test_gin_index_exists(): void
    {
        $indexes = DB::select("
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'transcript_segments'
            AND indexname = 'transcript_segments_body_gin'
        ");
        $this->assertNotEmpty($indexes);
    }

    public function test_search_requires_ready_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->state([
            'status' => MeetingStatus::Processing->value,
        ])->create();

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript?search=anything")
            ->assertStatus(409);
    }
}
