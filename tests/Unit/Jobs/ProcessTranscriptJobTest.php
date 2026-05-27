<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Events\MeetingStatusUpdated;
use App\Jobs\ProcessTranscriptJob;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\RecallAiService;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessTranscriptJobTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function fakeTranscript(): array
    {
        return [
            [
                'speaker' => 'Rep',
                'words' => [
                    ['text' => 'Hi', 'start_time' => 0.0, 'end_time' => 0.5],
                    ['text' => 'there', 'start_time' => 0.5, 'end_time' => 1.0],
                ],
            ],
            [
                'speaker' => 'Prospect',
                'words' => [
                    ['text' => 'Hello', 'start_time' => 1.2, 'end_time' => 1.6],
                ],
            ],
            [
                'speaker' => 'Rep',
                'words' => [
                    ['text' => 'How', 'start_time' => 2.0, 'end_time' => 2.3],
                    ['text' => 'are', 'start_time' => 2.3, 'end_time' => 2.5],
                    ['text' => 'you', 'start_time' => 2.5, 'end_time' => 2.8],
                ],
            ],
        ];
    }

    private function createProcessingMeeting(): Meeting
    {
        return Meeting::factory()
            ->for(User::factory())
            ->state([
                'status' => MeetingStatus::Processing->value,
                'recall_bot_id' => 'bot_test_123',
                'started_at' => now()->subMinutes(10),
                'ended_at' => now(),
            ])
            ->create();
    }

    private function mockRecallReturning(array $transcript): void
    {
        $this->mock(RecallAiService::class, function (MockInterface $mock) use ($transcript) {
            $mock->shouldReceive('getTranscript')->andReturn($transcript);
        });
    }

    public function test_parses_segments_and_inserts_into_db(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning($this->fakeTranscript());

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Ready->value,
        ]);

        $segments = TranscriptSegment::where('meeting_id', $meeting->id)
            ->orderBy('start_ms')
            ->get();
        $this->assertCount(3, $segments);
        $this->assertSame('Rep', $segments[0]->speaker_label);
        $this->assertSame('Hi there', $segments[0]->body);
        $this->assertSame(0, $segments[0]->start_ms);
        $this->assertSame(1000, $segments[0]->end_ms);
        $this->assertSame('Prospect', $segments[1]->speaker_label);
        $this->assertSame('How are you', $segments[2]->body);

        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'transcript.processed',
            'entity_id' => $meeting->id,
        ]);
    }

    public function test_calculates_talk_time(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning($this->fakeTranscript());

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $analysis = CoachingAnalysis::where('meeting_id', $meeting->id)->firstOrFail();

        // Rep speaking: (1000-0) + (2800-2000) = 1800ms
        // Prospect speaking: (1600-1200) = 400ms
        // Total: 2200ms → rep 1800/2200 = 82%, prospect 18%
        $this->assertSame(82, $analysis->talk_time_rep);
        $this->assertSame(18, $analysis->talk_time_prospect);
        $this->assertSame(100, $analysis->talk_time_rep + $analysis->talk_time_prospect);
    }

    public function test_is_idempotent(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning($this->fakeTranscript());

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $segmentCountAfterFirstRun = TranscriptSegment::where('meeting_id', $meeting->id)->count();

        // Second run: meeting is now Ready, so it should short-circuit
        (new ProcessTranscriptJob($meeting->fresh()))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $this->assertSame(
            $segmentCountAfterFirstRun,
            TranscriptSegment::where('meeting_id', $meeting->id)->count()
        );
        // Only one coaching analysis row created
        $this->assertSame(1, CoachingAnalysis::where('meeting_id', $meeting->id)->count());
    }

    public function test_broadcasts_status_update_after_processing(): void
    {
        Event::fake([MeetingStatusUpdated::class]);

        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning($this->fakeTranscript());

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        Event::assertDispatched(MeetingStatusUpdated::class, function (MeetingStatusUpdated $event) use ($meeting): bool {
            return $event->meeting->id === $meeting->id;
        });
    }

    public function test_merges_consecutive_same_speaker_words_into_one_segment(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning([
            [
                'speaker' => 'Rep',
                'words' => [
                    ['text' => 'Hello', 'start_time' => 0.0, 'end_time' => 0.5],
                    ['text' => 'world', 'start_time' => 0.6, 'end_time' => 1.0],
                    ['text' => 'today', 'start_time' => 1.1, 'end_time' => 1.5],
                ],
            ],
        ]);

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $segments = TranscriptSegment::where('meeting_id', $meeting->id)->get();
        $this->assertCount(1, $segments);
        $this->assertSame('Hello world today', $segments[0]->body);
    }

    public function test_splits_segments_when_speaker_changes(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning([
            [
                'speaker' => 'Rep',
                'words' => [['text' => 'Hi', 'start_time' => 0.0, 'end_time' => 0.5]],
            ],
            [
                'speaker' => 'Prospect',
                'words' => [['text' => 'Hello', 'start_time' => 0.6, 'end_time' => 1.1]],
            ],
            [
                'speaker' => 'Rep',
                'words' => [['text' => 'Great', 'start_time' => 1.2, 'end_time' => 1.6]],
            ],
        ]);

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $segments = TranscriptSegment::where('meeting_id', $meeting->id)->orderBy('start_ms')->get();
        $this->assertCount(3, $segments);
        $this->assertSame('Rep', $segments[0]->speaker_label);
        $this->assertSame('Prospect', $segments[1]->speaker_label);
        $this->assertSame('Rep', $segments[2]->speaker_label);
    }

    public function test_splits_segment_on_large_gap_even_for_same_speaker(): void
    {
        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning([
            [
                'speaker' => 'Rep',
                'words' => [
                    ['text' => 'Hello', 'start_time' => 0.0, 'end_time' => 0.5],
                    // Same speaker but a 5 second pause (> 2000ms gap)
                    ['text' => 'continuing', 'start_time' => 5.5, 'end_time' => 6.0],
                ],
            ],
        ]);

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $segments = TranscriptSegment::where('meeting_id', $meeting->id)->orderBy('start_ms')->get();
        $this->assertCount(2, $segments);
        $this->assertSame('Hello', $segments[0]->body);
        $this->assertSame('continuing', $segments[1]->body);
    }

    public function test_creates_coaching_shell_row_after_processing(): void
    {
        // Prevent the downstream CoachingAnalysisJob from running (and either
        // completing or failing) the shell row before we inspect it.
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\CoachingAnalysisJob::class]);

        $meeting = $this->createProcessingMeeting();
        $this->mockRecallReturning($this->fakeTranscript());

        (new ProcessTranscriptJob($meeting))->handle(
            app(RecallAiService::class),
            app(AuditService::class)
        );

        $shell = CoachingAnalysis::where('meeting_id', $meeting->id)->firstOrFail();
        $this->assertNull($shell->completed_at);
        $this->assertNull($shell->failed_at);
        $this->assertSame('auto', $shell->triggered_by);
        $this->assertNotNull($shell->talk_time_rep);
        $this->assertNotNull($shell->talk_time_prospect);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
