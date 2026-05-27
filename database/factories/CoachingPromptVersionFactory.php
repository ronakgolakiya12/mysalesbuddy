<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachingPromptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachingPromptVersion>
 */
class CoachingPromptVersionFactory extends Factory
{
    protected $model = CoachingPromptVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt_text' => 'You are a sales coach. Analyze the transcript and produce structured JSON.',
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
