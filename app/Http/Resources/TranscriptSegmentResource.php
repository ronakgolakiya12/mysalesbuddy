<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TranscriptSegment;
use Illuminate\Http\Request;

/**
 * @mixin TranscriptSegment
 */
class TranscriptSegmentResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TranscriptSegment $segment */
        $segment = $this->resource;

        return [
            'id' => $segment->id,
            'speaker_label' => $segment->speaker_label,
            'body' => $segment->body,
            'start_ms' => (int) $segment->start_ms,
            'end_ms' => (int) $segment->end_ms,
        ];
    }
}
