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
use Illuminate\Database\Eloquent\Collection;
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

        /** @var Collection<int, \App\Models\TranscriptSegment> $segments */
        $segments = $meeting->transcriptSegments()
            ->orderBy('start_ms')
            ->get(['id', 'speaker_label', 'body', 'start_ms', 'end_ms']);

        if ($segments->isEmpty()) {
            $this->failAnalysis($analysis, 'No transcript segments available for analysis.');

            return;
        }

        [$talkTimeRep, $talkTimeProspect] = $this->computeTalkTime(
            $segments,
            (string) $meeting->user->name
        );

        $payload = $segments
            ->map(static fn ($s) => [
                'speaker_label' => $s->speaker_label,
                'body' => $s->body,
                'start_ms' => (int) $s->start_ms,
            ])
            ->all();

        $transcriptText = $segments->pluck('body')->implode("\n");

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
                $payload,
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
            'talk_time_rep' => $talkTimeRep,
            'talk_time_prospect' => $talkTimeProspect,
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
     * Validate that the model output conforms to the contract the frontend renders.
     *
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function validateOutput(array $output): array
    {
        $this->requireKeys($output, [
            'overall_score' => 'is_int_or_numeric_string',
            'one_liner' => 'is_string',
            'rationale' => 'is_string',
            'next_step_clarity' => 'is_clarity',
            'next_step_detail' => 'is_string',
            'discovery_quality' => 'is_array',
            'objection_handling' => 'is_array',
            'strengths' => 'is_array',
            'opportunities' => 'is_array',
        ], 'root');

        $score = (int) $output['overall_score'];
        $output['overall_score'] = max(1, min(10, $score));

        $output['discovery_quality'] = $this->validateDiscoveryQuality($output['discovery_quality']);
        $output['objection_handling'] = $this->validateObjectionHandling($output['objection_handling']);
        $output['strengths'] = $this->validateStrengths($output['strengths']);
        $output['opportunities'] = $this->validateOpportunities($output['opportunities']);

        return $output;
    }

    /**
     * @param  array<string, mixed>  $dq
     * @return array<string, mixed>
     */
    private function validateDiscoveryQuality(array $dq): array
    {
        $this->requireKeys($dq, [
            'pain_uncovered' => 'is_bool',
            'impact_quantified' => 'is_bool',
            'decision_process_explored' => 'is_bool',
            'timeline_confirmed' => 'is_bool',
            'missed_areas' => 'is_array',
        ], 'discovery_quality');

        $dq['missed_areas'] = array_values(array_filter(
            $dq['missed_areas'],
            static fn ($v): bool => is_string($v) && $v !== '',
        ));

        return $dq;
    }

    /**
     * @param  array<string, mixed>  $oh
     * @return array<string, mixed>
     */
    private function validateObjectionHandling(array $oh): array
    {
        $this->requireKeys($oh, [
            'summary' => 'is_string',
            'objections' => 'is_array',
        ], 'objection_handling');

        $oh['objections'] = array_values(array_map(
            function ($obj, int $i): array {
                if (! is_array($obj)) {
                    throw new CoachingOutputInvalidException("objection_handling.objections[{$i}] must be an object.");
                }
                $this->requireKeys($obj, [
                    'objection' => 'is_string',
                    'response_summary' => 'is_string',
                    'resolved' => 'is_bool',
                ], "objection_handling.objections[{$i}]");

                $obj['evidence'] = $this->validateEvidence(
                    $obj['evidence'] ?? null,
                    "objection_handling.objections[{$i}].evidence"
                );

                return $obj;
            },
            $oh['objections'],
            array_keys($oh['objections']),
        ));

        return $oh;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function validateStrengths(array $items): array
    {
        $count = count($items);
        if ($count < 2 || $count > 4) {
            throw new CoachingOutputInvalidException("strengths must contain 2-4 items, got {$count}.");
        }

        return array_values(array_map(
            function ($item, int $i): array {
                if (! is_array($item)) {
                    throw new CoachingOutputInvalidException("strengths[{$i}] must be an object.");
                }
                $this->requireKeys($item, [
                    'title' => 'is_string',
                    'detail' => 'is_string',
                ], "strengths[{$i}]");

                $item['evidence'] = $this->validateEvidence(
                    $item['evidence'] ?? null,
                    "strengths[{$i}].evidence"
                );

                return $item;
            },
            $items,
            array_keys($items),
        ));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function validateOpportunities(array $items): array
    {
        $count = count($items);
        if ($count < 2 || $count > 4) {
            throw new CoachingOutputInvalidException("opportunities must contain 2-4 items, got {$count}.");
        }

        return array_values(array_map(
            function ($item, int $i): array {
                if (! is_array($item)) {
                    throw new CoachingOutputInvalidException("opportunities[{$i}] must be an object.");
                }
                $this->requireKeys($item, [
                    'title' => 'is_string',
                    'detail' => 'is_string',
                    'suggestion' => 'is_string',
                ], "opportunities[{$i}]");

                $item['evidence'] = $this->validateEvidence(
                    $item['evidence'] ?? null,
                    "opportunities[{$i}].evidence"
                );

                return $item;
            },
            $items,
            array_keys($items),
        ));
    }

    /**
     * Evidence is allowed to be null (model couldn't cite). But if present, it must conform.
     *
     * @param  mixed  $evidence
     * @return array<string, mixed>|null
     */
    private function validateEvidence($evidence, string $path): ?array
    {
        if ($evidence === null) {
            return null;
        }
        if (! is_array($evidence)) {
            throw new CoachingOutputInvalidException("{$path} must be an object or null.");
        }

        $this->requireKeys($evidence, [
            'speaker' => 'is_string',
            'timestamp_ms' => 'is_int_or_numeric_string',
            'quote' => 'is_string',
        ], $path);

        $evidence['timestamp_ms'] = (int) $evidence['timestamp_ms'];

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $haystack
     * @param  array<string, string>  $required
     */
    private function requireKeys(array $haystack, array $required, string $path): void
    {
        foreach ($required as $key => $check) {
            if (! array_key_exists($key, $haystack)) {
                throw new CoachingOutputInvalidException("{$path}: missing required key '{$key}'.");
            }
            if (! $this->checkType($haystack[$key], $check)) {
                throw new CoachingOutputInvalidException("{$path}.{$key}: invalid type (expected {$check}).");
            }
        }
    }

    private function checkType(mixed $value, string $check): bool
    {
        return match ($check) {
            'is_string' => is_string($value),
            'is_bool' => is_bool($value),
            'is_array' => is_array($value),
            'is_int_or_numeric_string' => is_int($value) || (is_string($value) && is_numeric($value)),
            'is_clarity' => is_string($value) && in_array($value, ['clear', 'vague', 'missing'], true),
            default => false,
        };
    }

    /**
     * Identify the rep speaker (best match against the authenticated user's name, or fall back
     * to the first speaker to appear) and return [rep_pct, prospect_pct] as integers 0-100.
     *
     * @param  Collection<int, \App\Models\TranscriptSegment>  $segments
     * @return array{0: ?int, 1: ?int}
     */
    private function computeTalkTime(Collection $segments, string $userName): array
    {
        $totals = [];
        $firstSpeaker = null;
        foreach ($segments as $s) {
            $label = (string) $s->speaker_label;
            $firstSpeaker ??= $label;
            $duration = max(0, ((int) $s->end_ms) - ((int) $s->start_ms));
            $totals[$label] = ($totals[$label] ?? 0) + $duration;
        }

        $total = array_sum($totals);
        if ($total <= 0) {
            return [null, null];
        }

        $repLabel = $this->matchRepLabel(array_keys($totals), $userName) ?? $firstSpeaker;
        if ($repLabel === null) {
            return [null, null];
        }

        $repMs = $totals[$repLabel] ?? 0;
        $repPct = (int) round(($repMs / $total) * 100);
        $repPct = max(0, min(100, $repPct));
        $prospectPct = 100 - $repPct;

        return [$repPct, $prospectPct];
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function matchRepLabel(array $labels, string $userName): ?string
    {
        $needle = trim(mb_strtolower($userName));
        if ($needle === '') {
            return null;
        }
        foreach ($labels as $label) {
            $hay = mb_strtolower($label);
            if ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $label;
            }
        }
        $firstToken = explode(' ', $needle)[0] ?? '';
        if ($firstToken !== '') {
            foreach ($labels as $label) {
                if (str_contains(mb_strtolower($label), $firstToken)) {
                    return $label;
                }
            }
        }

        return null;
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
