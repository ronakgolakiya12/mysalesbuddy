<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\PurgeSoftDeletedMeetingsJob;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeSoftDeletedMeetingsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deletes_meetings_soft_deleted_more_than_90_days_ago(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();

        TranscriptSegment::query()->create([
            'meeting_id' => $meeting->id,
            'speaker_label' => 'Rep',
            'body' => 'hi',
            'start_ms' => 0,
            'end_ms' => 100,
        ]);
        CoachingAnalysis::factory()->for($meeting)->completed()->create();

        $meeting->delete();
        Meeting::withTrashed()->where('id', $meeting->id)->update([
            'deleted_at' => now()->subDays(91),
        ]);

        (new PurgeSoftDeletedMeetingsJob())->handle();

        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
        $this->assertDatabaseMissing('transcript_segments', ['meeting_id' => $meeting->id]);
        $this->assertDatabaseMissing('coaching_analyses', ['meeting_id' => $meeting->id]);
    }

    public function test_keeps_meetings_soft_deleted_recently(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();
        $meeting->delete();
        Meeting::withTrashed()->where('id', $meeting->id)->update([
            'deleted_at' => now()->subDays(30),
        ]);

        (new PurgeSoftDeletedMeetingsJob())->handle();

        $this->assertNotNull(Meeting::withTrashed()->find($meeting->id));
    }
}
