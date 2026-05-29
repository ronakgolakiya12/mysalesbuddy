<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotetakerConfig;
use Illuminate\Http\Request;

/**
 * @mixin NotetakerConfig
 */
class NotetakerConfigResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var NotetakerConfig $config */
        $config = $this->resource;

        return [
            'id' => $config->id,
            'user_id' => $config->user_id,
            'display_name' => $config->display_name,
            'avatar_path' => $config->avatar_path,
            'avatar_url' => $config->avatarUrl(),
            'intro_message' => $config->intro_message,
            'default_scope' => $config->default_scope->value,
            'created_at' => $config->created_at->toIso8601String(),
            'updated_at' => $config->updated_at->toIso8601String(),
        ];
    }
}
