<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CoachingPromptVersion;
use Illuminate\Http\Request;

/**
 * @mixin CoachingPromptVersion
 */
class CoachingPromptVersionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CoachingPromptVersion $version */
        $version = $this->resource;

        return [
            'id' => $version->id,
            'prompt_text' => $version->prompt_text,
            'is_active' => $version->is_active,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
