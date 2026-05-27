<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Meeting;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use Illuminate\Http\Request;

/**
 * @mixin Meeting
 */
class MeetingResource extends ApiResource
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
            'user_id' => $meeting->user_id,
            'external_meeting_url' => $meeting->external_meeting_url,
            'title' => $meeting->title,
            'provider' => $meeting->provider instanceof MeetingProvider
                ? $meeting->provider->value
                : (string) $meeting->provider,
            'status' => $meeting->status instanceof MeetingStatus
                ? $meeting->status->value
                : (string) $meeting->status,
            'scope' => $meeting->scope instanceof MeetingScope
                ? $meeting->scope->value
                : (string) $meeting->scope,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'started_at' => $meeting->started_at?->toIso8601String(),
            'ended_at' => $meeting->ended_at?->toIso8601String(),
            'duration_seconds' => $meeting->duration_seconds !== null
                ? (int) $meeting->duration_seconds
                : null,
            'duration_formatted' => $meeting->durationFormatted(),
            'transcript_segments' => TranscriptSegmentResource::collection(
                $this->whenLoaded('transcriptSegments')
            ),
            'latest_coaching_analysis' => $this->whenLoaded(
                'latestCoachingAnalysis',
                fn () => $meeting->latestCoachingAnalysis
                    ? new CoachingAnalysisResource($meeting->latestCoachingAnalysis)
                    : null
            ),
            'created_at' => $meeting->created_at?->toIso8601String(),
            'updated_at' => $meeting->updated_at?->toIso8601String(),
        ];
    }
}
