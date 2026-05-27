<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Support\Enums\CoachingMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachingAnalysis>
 */
class CoachingAnalysisFactory extends Factory
{
    protected $model = CoachingAnalysis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'prompt_version_id' => null,
            'mode' => CoachingMode::TranscriptOnly->value,
            'deal_context' => null,
            'overall_score' => null,
            'talk_time_rep' => null,
            'talk_time_prospect' => null,
            'output_json' => null,
            'triggered_by' => 'manual',
            'completed_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'overall_score' => 8,
            'output_json' => [
                'overall_score' => 8,
                'summary' => 'Good discovery call.',
                'strengths' => ['Asked open-ended questions'],
                'improvements' => ['Reduce filler words'],
                'questions_asked' => ['What is your biggest challenge?'],
                'objections' => [],
                'next_steps' => ['Send proposal next week'],
            ],
            'completed_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    /**
     * Load `output_json` from tests/Fixtures/coaching_output.json — used by
     * Phase 8 tests that want the full schema as produced by the real prompt.
     */
    public function completedFromFixture(): self
    {
        return $this->state(function () {
            $fixturePath = base_path('tests/Fixtures/coaching_output.json');
            $output = is_file($fixturePath)
                ? (array) (json_decode((string) file_get_contents($fixturePath), true) ?? [])
                : [];

            return [
                'overall_score' => (int) ($output['overall_score'] ?? 8),
                'output_json' => $output,
                'talk_time_rep' => 55,
                'talk_time_prospect' => 45,
                'completed_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'failed_at' => now(),
            'failure_reason' => 'OpenAI call failed: rate limit exceeded',
            'completed_at' => null,
        ]);
    }
}
