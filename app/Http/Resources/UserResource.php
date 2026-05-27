<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
class UserResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $notetakerConfig = $user->relationLoaded('notetakerConfig') && $user->notetakerConfig !== null
            ? new NotetakerConfigResource($user->notetakerConfig)
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'has_google_calendar' => $user->oauthConnections()->where('provider', 'google')->exists(),
            'has_microsoft_calendar' => $user->oauthConnections()->where('provider', 'microsoft')->exists(),
            'notetaker_config' => $notetakerConfig,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
