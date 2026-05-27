<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CoachingPromptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CoachingPromptService
{
    public function getActivePrompt(User $user): CoachingPromptVersion
    {
        $prompt = CoachingPromptVersion::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if ($prompt === null) {
            throw new RuntimeException('No active coaching prompt configured for user '.$user->id);
        }

        return $prompt;
    }

    public function createVersion(User $user, string $promptText): CoachingPromptVersion
    {
        return DB::transaction(function () use ($user, $promptText): CoachingPromptVersion {
            CoachingPromptVersion::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return CoachingPromptVersion::create([
                'user_id' => $user->id,
                'prompt_text' => $promptText,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @return Collection<int, CoachingPromptVersion>
     */
    public function getVersionHistory(User $user): Collection
    {
        return CoachingPromptVersion::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function restoreVersion(User $user, CoachingPromptVersion $version): CoachingPromptVersion
    {
        if ($version->user_id !== $user->id) {
            throw new RuntimeException('Prompt version does not belong to this user.');
        }

        return $this->createVersion($user, (string) $version->prompt_text);
    }
}
