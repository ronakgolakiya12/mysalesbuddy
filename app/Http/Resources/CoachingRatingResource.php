<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CoachingRating;
use Illuminate\Http\Request;

/**
 * @mixin CoachingRating
 */
class CoachingRatingResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CoachingRating $rating */
        $rating = $this->resource;

        return [
            'id' => $rating->id,
            'section_key' => $rating->section_key,
            'rating' => $rating->rating->value,
        ];
    }
}
