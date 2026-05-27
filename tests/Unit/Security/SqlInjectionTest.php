<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcript_search_uses_parameter_binding_and_does_not_execute_payload(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        TranscriptSegment::factory()->count(3)->create(['meeting_id' => $meeting->id]);

        $payload = "%'); DROP TABLE meetings; --";

        $response = $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/transcript?search=".urlencode($payload));

        $response->assertStatus(200);

        // The meetings table must still exist.
        $this->assertTrue(\Schema::hasTable('meetings'));
    }

    public function test_meeting_index_search_does_not_execute_payload(): void
    {
        $user = User::factory()->create();
        Meeting::factory()->count(2)->create(['user_id' => $user->id]);

        $payload = "%'; DELETE FROM meetings WHERE '1'='1";

        $this->actingAs($user)
            ->getJson('/api/meetings?search='.urlencode($payload))
            ->assertStatus(200);

        $this->assertSame(2, Meeting::query()->where('user_id', $user->id)->count());
    }
}
