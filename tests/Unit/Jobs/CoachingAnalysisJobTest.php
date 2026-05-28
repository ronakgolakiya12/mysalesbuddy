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
use App\Services\Ai\AiServiceInterface;
use App\Services\AuditService;
use App\Services\CoachingPromptService;
use App\Services\OpenAiService;
use App\Support\Enums\CoachingMode;
use Illuminate\Support\Facades\DB;
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
        $evidence = [
            'speaker' => 'Rep',
            'timestamp_ms' => 15000,
            'quote' => 'Walk me through your typical week.',
        ];

        return [
            'overall_score' => 8,
            'one_liner' => 'Strong discovery with a clear next step.',
            'rationale' => 'Rep led with open questions and locked a follow-up.',
            'next_step_clarity' => 'clear',
            'next_step_detail' => 'Send proposal by Friday.',
            'discovery_quality' => [
                'pain_uncovered' => true,
                'impact_quantified' => true,
                'decision_process_explored' => true,
                'timeline_confirmed' => false,
                'missed_areas' => ['budget'],
            ],
            'objection_handling' => [
                'summary' => 'No major objections.',
                'objections' => [],
            ],
            'strengths' => [
                ['title' => 'Open-ended discovery', 'detail' => 'Asked good questions.', 'evidence' => $evidence],
                ['title' => 'Quantified impact', 'detail' => 'Translated pain to dollars.', 'evidence' => $evidence],
            ],
            'opportunities' => [
                ['title' => 'Budget check skipped', 'detail' => 'Did not confirm budget.', 'suggestion' => 'Ask earlier.', 'evidence' => $evidence],
                ['title' => 'Filler words', 'detail' => 'Frequent ums.', 'suggestion' => 'Pause instead.', 'evidence' => $evidence],
            ],
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
        $this->assertSame('Strong discovery with a clear next step.', $analysis->output_json['one_liner']);

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
                    Carbon::setTestNow(Carbon::now()->addSeconds(61));

                    return $this->validOutput();
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

    public function test_job_uses_interface_not_concrete_class(): void
    {
        // Bind the interface directly so the factory is bypassed.
        // If the job still depends on OpenAiService, this mock won't be called.
        $this->app->bind(AiServiceInterface::class, function () {
            $mock = Mockery::mock(AiServiceInterface::class);
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn($this->validOutput());

            return $mock;
        });

        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(AiServiceInterface::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $analysis->refresh();
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_provider_used_stored_on_analysis(): void
    {
        config(['services.ai.provider' => 'gemini']);

        $this->mock(\App\Services\GeminiAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn($this->validOutput());
        });

        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(AiServiceInterface::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $this->assertDatabaseHas('coaching_analyses', [
            'id' => $analysis->id,
            'provider_used' => 'gemini',
        ]);
    }

    public function test_provider_logged_in_audit_on_completion(): void
    {
        config(['services.ai.provider' => 'openai']);

        $this->mock(OpenAiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('estimateTokens')->andReturn(50);
            $mock->shouldReceive('analyzeTranscript')->andReturn($this->validOutput());
        });

        $user = User::factory()->create();
        $this->activePrompt($user);
        $meeting = $this->makeMeetingWithTranscript($user);
        $analysis = $this->makeAnalysisShell($meeting);

        (new CoachingAnalysisJob($meeting, $analysis->id, CoachingMode::TranscriptOnly->value))
            ->handle(
                app(AiServiceInterface::class),
                app(CoachingPromptService::class),
                app(AuditService::class)
            );

        $log = DB::table('audit_log')
            ->where('entity_id', $analysis->id)
            ->where('event_type', 'coaching.completed')
            ->first();

        $this->assertNotNull($log);
        $metadata = json_decode((string) $log->metadata_json, true);
        $this->assertSame('openai', $metadata['ai_provider'] ?? null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}
