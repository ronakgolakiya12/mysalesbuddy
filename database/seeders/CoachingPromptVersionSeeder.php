<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CoachingPromptVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class CoachingPromptVersionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'otto@mysalesbuddy.dev')->firstOrFail();

        $promptText = (string) config('coaching.default_prompt');

        CoachingPromptVersion::updateOrCreate(
            ['user_id' => $user->id, 'is_active' => true],
            ['prompt_text' => $promptText],
        );
    }
}
