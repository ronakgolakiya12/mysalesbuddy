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
            'talk_time_rep' => 45,
            'talk_time_prospect' => 55,
            'output_json' => [
                'overall_score' => 8,
                'one_liner' => 'Strong discovery with a clearly committed next step.',
                'rationale' => 'The rep led with open-ended questions and locked a follow-up.',
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
                    'summary' => 'Acknowledged the existing vendor and pivoted to renewal timing.',
                    'objections' => [],
                ],
                'strengths' => [
                    [
                        'title' => 'Open-ended discovery',
                        'detail' => 'Surfaced pain without leading.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 15000,
                            'quote' => 'Walk me through your typical week.',
                        ],
                    ],
                    [
                        'title' => 'Quantified impact',
                        'detail' => 'Translated inefficiency into dollars.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 240000,
                            'quote' => 'About 12 hours a week.',
                        ],
                    ],
                ],
                'opportunities' => [
                    [
                        'title' => 'Skipped budget check',
                        'detail' => 'Never confirmed budget exists this quarter.',
                        'suggestion' => 'Ask about budget before proposing.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 360000,
                            'quote' => "Let's talk pricing on the next call.",
                        ],
                    ],
                    [
                        'title' => 'Filler words',
                        'detail' => 'Frequent ums during pricing.',
                        'suggestion' => 'Pause silently instead of filling.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 420000,
                            'quote' => 'Um, so, like, pricing depends.',
                        ],
                    ],
                ],
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
