<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CoachingAnalysis;
use App\Support\Enums\CoachingMode;
use Illuminate\Http\Request;

/**
 * @mixin CoachingAnalysis
 */
class CoachingAnalysisResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CoachingAnalysis $analysis */
        $analysis = $this->resource;

        $mode = $analysis->mode;

        return [
            'id' => $analysis->id,
            'meeting_id' => $analysis->meeting_id,
            'mode' => $mode instanceof CoachingMode ? $mode->value : (string) $mode,
            'deal_context' => $analysis->deal_context,
            'overall_score' => $analysis->overall_score,
            'talk_time_rep' => $analysis->talk_time_rep,
            'talk_time_prospect' => $analysis->talk_time_prospect,
            'summary' => $analysis->output_json['summary'] ?? null,
            'strengths' => $analysis->output_json['strengths'] ?? null,
            'improvements' => $analysis->output_json['improvements'] ?? null,
            'questions_asked' => $analysis->output_json['questions_asked'] ?? null,
            'objections' => $analysis->output_json['objections'] ?? null,
            'next_steps' => $analysis->output_json['next_steps'] ?? null,
            'triggered_by' => $analysis->triggered_by,
            'completed_at' => $analysis->completed_at?->toIso8601String(),
            'failed_at' => $analysis->failed_at?->toIso8601String(),
            'failure_reason' => $analysis->failure_reason,
            'created_at' => $analysis->created_at?->toIso8601String(),
            'ratings' => CoachingRatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
