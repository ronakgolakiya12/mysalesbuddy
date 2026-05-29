<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;

/**
 * @mixin Meeting
 */
class MeetingListResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Meeting $meeting */
        $meeting = $this->resource;

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'provider' => $meeting->provider->value,
            'status' => $meeting->status->value,
            'scope' => $meeting->scope->value,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'started_at' => $meeting->started_at?->toIso8601String(),
            'ended_at' => $meeting->ended_at?->toIso8601String(),
            'duration_seconds' => $meeting->duration_seconds,
            'overall_score' => $this->resolveOverallScore($meeting),
            'created_at' => $meeting->created_at->toIso8601String(),
        ];
    }

    private function resolveOverallScore(Meeting $meeting): ?int
    {
        if (! $meeting->relationLoaded('latestCoachingAnalysis')) {
            return null;
        }
        $analysis = $meeting->latestCoachingAnalysis;
        if ($analysis === null || $analysis->completed_at === null || $analysis->overall_score === null) {
            return null;
        }

        $score = (int) $analysis->overall_score;

        // Historic rows from before the 0-100 → 1-10 scale change may still
        // carry legacy values. Treat anything > 10 as a 0-100 score and
        // normalise; otherwise return as-is.
        if ($score > 10) {
            $score = (int) round($score / 10);
        }

        return max(1, min(10, $score));
    }
}
