<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Events\CoachingAnalysisCompleted;
use App\Exceptions\TranscriptTooLargeException;
use App\Jobs\CoachingAnalysisJob;
use App\Models\CoachingAnalysis;
use App\Models\CoachingPromptVersion;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CoachingPromptService;
use App\Services\OpenAiService;
use App\Support\Enums\CoachingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CoachingAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeMeetingWithTranscript(?User $user = null): Meeting
    {
        $user ??= User::factory()->create();

        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);

        TranscriptSegment::insert([
            [
                'id' => (string) Str::uuid(),
                'meeting_id' => $meeting->id,
                'speaker_label' => 'Rep',
                'body' => 'Hello and welcome.',
                'start_ms' => 0,
                'end_ms' => 2000,
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'meeting_id' => $meeting->id,
                'speaker_label' => 'Prospect',
                'body' => 'Glad to be here.',
                'start_ms' => 2500,
                'end_ms' => 4500,
                'created_at' => now(),
            ],
        ]);

        return $meeting;
    }

    private function activePrompt(User $user): CoachingPromptVersion
    {
        return CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    private function makeAnalysisShell(Meeting $meeting): CoachingAnalysis
    {
        return CoachingAnalysis::create([
            'meeting_id' => $meeting->id,
            'mode' => CoachingMode::TranscriptOnly,
            'triggered_by' => 'manual',
        ]);
    }

    /** @return array<string, mixed> */
    private function validOutput(): array
    {
        return [
            'overall_score' => 8,
            'summary' => 'Good discovery call.',
            'strengths' => ['Open-ended questions'],
            'improvements' => ['Reduce filler'],
            'questions_asked' => ['Q1?'],
            'objections' => [],
            'next_steps' => ['Follow up'],
        ];
    }

    public function test_completes_analysis_and_broadcasts_event(): void
    {
        Event::fake([CoachingAnalysisCompleted::class]);

        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn($this->validOutput());
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertNotNull($analysis->completed_at);
        $this->assertSame(8, $analysis->overall_score);
        $this->assertSame('Good discovery call.', $analysis->output_json['summary']);

        $this->assertDatabaseHas('audit_log', [
            'event_type' => 'coaching.completed',
            'entity_id' => $analysis->id,
        ]);

        Event::assertDispatched(CoachingAnalysisCompleted::class, function (CoachingAnalysisCompleted $e) use ($analysis): bool {
            return $e->analysis->id === $analysis->id;
        });
    }

    public function test_is_idempotent_when_already_completed(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);
        $analysis->completed_at = now();
        $analysis->save();

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('analyzeTranscript');
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $this->assertTrue(true);
    }

    public function test_fails_when_no_transcript_segments(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('analyzeTranscript');
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertNotNull($analysis->failed_at);
        $this->assertStringContainsString('No transcript segments', (string) $analysis->failure_reason);
    }

    public function test_fails_when_transcript_too_large(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andThrow(new TranscriptTooLargeException('too big'));
            $mock->shouldNotReceive('analyzeTranscript');
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertNotNull($analysis->failed_at);
        $this->assertStringContainsString('Transcript too large', (string) $analysis->failure_reason);
    }

    public function test_fails_when_output_is_invalid(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn(['summary' => 'incomplete']);
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertNotNull($analysis->failed_at);
        $this->assertStringContainsString('Invalid coaching output', (string) $analysis->failure_reason);
    }

    public function test_logs_warning_when_sla_exceeded(): void
    {
        Event::fake([CoachingAnalysisCompleted::class]);
        Log::spy();

        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        Carbon::setTestNow(Carbon::parse('2026-05-26 12:00:00'));

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')
                ->andReturnUsing(function (): array {
                    // Simulate 61 seconds of elapsed time during OpenAI call.
                    Carbon::setTestNow(Carbon::now()->addSeconds(61));

                    return [
                        'overall_score' => 7,
                        'summary' => 'Slow.',
                        'strengths' => [],
                        'improvements' => [],
                        'questions_asked' => [],
                        'objections' => [],
                        'next_steps' => [],
                    ];
                });
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        Log::shouldHaveReceived('warning')
            ->with('Coaching analysis exceeded SLA', Mockery::on(function (array $ctx): bool {
                return ($ctx['elapsed_seconds'] ?? 0) >= 60;
            }));

        // Ensure analysis still completed despite the SLA warning.
        $analysis->refresh();
        $this->assertNotNull($analysis->completed_at);

        Carbon::setTestNow();
    }

    public function test_pins_prompt_version_used_for_analysis(): void
    {
        $user = User::factory()->create();
        $prompt = $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn($this->validOutput());
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertSame($prompt->id, $analysis->prompt_version_id);
    }

    public function test_passes_deal_context_to_openai_when_provided(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $captured = null;
        $this->mock(OpenAiService::class, function (MockInterface $mock) use (&$captured): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')
                ->andReturnUsing(function ($prompt, $segments, $dealContext) use (&$captured) {
                    $captured = $dealContext;

                    return $this->validOutput();
                });
        });

        $deal = 'Acme — $50k ARR — Sales VP champion';
        (new CoachingAnalysisJob(
            $meeting,
            $analysis->id,
            CoachingMode::DiscoveryAware->value,
            $deal
        ))->handle(
            app(OpenAiService::class),
            app(CoachingPromptService::class),
            app(AuditService::class)
        );

        $this->assertSame($deal, $captured);
    }

    public function test_clamps_overall_score_to_range(): void
    {
        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn(array_merge($this->validOutput(), [
                'overall_score' => 42,
            ]));
        });

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(OpenAiService::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertSame(10, $analysis->overall_score);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}
