<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\CoachingAnalysisCompleted;
use App\Exceptions\CoachingOutputInvalidException;
use App\Exceptions\TranscriptTooLargeException;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Services\AuditService;
use App\Services\CoachingPromptService;
use App\Services\OpenAiService;
use App\Support\Enums\AuditEventType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CoachingAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public Meeting $meeting,
        public string $analysisId,
        public string $mode,
        public ?string $dealContext = null
    ) {
        $this->onQueue('default');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(OpenAiService $openAi, CoachingPromptService $promptService, AuditService $audit): void
    {
        /** @var CoachingAnalysis|null $analysis */
        $analysis = CoachingAnalysis::query()->find($this->analysisId);
        if ($analysis === null) {
            return;
        }

        // Idempotency
        if ($analysis->completed_at !== null) {
            return;
        }

        $meeting = $this->meeting->fresh();
        if ($meeting === null) {
            $this->failAnalysis($analysis, 'Meeting no longer exists.');

            return;
        }

        try {
            $prompt = $promptService->getActivePrompt($meeting->user);
        } catch (Throwable $e) {
            $this->failAnalysis($analysis, 'No active coaching prompt: '.$e->getMessage());

            return;
        }

        $segments = $meeting->transcriptSegments()
            ->orderBy('start_ms')
            ->get([
                'id', 'speaker_label', 'body', 'start_ms', 'end_ms',
            ])
            ->map(static fn ($s) => [
                'speaker_label' => $s->speaker_label,
                'body' => $s->body,
                'start_ms' => $s->start_ms,
            ])
            ->all();

        if ($segments === []) {
            $this->failAnalysis($analysis, 'No transcript segments available for analysis.');

            return;
        }

        $transcriptText = collect($segments)
            ->map(static fn (array $s): string => (string) $s['body'])
            ->implode("\n");

        try {
            $openAi->estimateTokens($transcriptText);
        } catch (TranscriptTooLargeException $e) {
            $this->failAnalysis($analysis, 'Transcript too large: '.$e->getMessage());

            return;
        }

        $startedAt = Carbon::now();

        try {
            $output = $openAi->analyzeTranscript(
                (string) $prompt->prompt_text,
                $segments,
                $this->dealContext
            );
        } catch (Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $this->failAnalysis($analysis, 'OpenAI call failed: '.$e->getMessage());

                return;
            }

            throw $e;
        }

        $elapsed = (int) abs($startedAt->diffInSeconds(Carbon::now()));
        if ($elapsed > 60) {
            Log::warning('Coaching analysis exceeded SLA', [
                'analysis_id' => $analysis->id,
                'meeting_id' => $meeting->id,
                'elapsed_seconds' => $elapsed,
            ]);
        }

        try {
            $output = $this->validateOutput($output);
        } catch (CoachingOutputInvalidException $e) {
            $this->failAnalysis($analysis, 'Invalid coaching output: '.$e->getMessage());

            return;
        }

        $analysis->fill([
            'prompt_version_id' => $prompt->id,
            'overall_score' => (int) $output['overall_score'],
            'output_json' => $output,
            'completed_at' => Carbon::now(),
        ])->save();

        $audit->log(
            user: $meeting->user,
            event: AuditEventType::CoachingCompleted,
            entityType: 'coaching_analysis',
            entityId: (string) $analysis->id,
            metadata: [
                'meeting_id' => $meeting->id,
                'mode' => $this->mode,
                'elapsed_seconds' => $elapsed,
                'overall_score' => $analysis->overall_score,
            ]
        );

        broadcast(new CoachingAnalysisCompleted($meeting, $analysis->fresh()));

        NotifyCoachingReadyJob::dispatch($meeting, (int) $analysis->overall_score);
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function validateOutput(array $output): array
    {
        $required = [
            'overall_score' => 'is_numeric',
            'summary' => 'is_string',
            'strengths' => 'is_array',
            'improvements' => 'is_array',
            'questions_asked' => 'is_array',
            'objections' => 'is_array',
            'next_steps' => 'is_array',
        ];

        foreach ($required as $key => $check) {
            if (! array_key_exists($key, $output)) {
                throw new CoachingOutputInvalidException("Missing required key: {$key}");
            }
            if (! $check($output[$key])) {
                throw new CoachingOutputInvalidException("Invalid type for key: {$key}");
            }
        }

        $score = (int) $output['overall_score'];
        $output['overall_score'] = max(1, min(10, $score));

        return $output;
    }

    private function failAnalysis(CoachingAnalysis $analysis, string $reason): void
    {
        $analysis->fill([
            'failed_at' => Carbon::now(),
            'failure_reason' => $reason,
        ])->save();

        Log::error('Coaching analysis failed', [
            'analysis_id' => $analysis->id,
            'reason' => $reason,
        ]);

        $this->fail(new RuntimeException($reason));
    }
}
