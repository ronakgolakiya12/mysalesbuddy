<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotetakerConfig;
use App\Models\User;
use App\Support\Enums\MeetingScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotetakerConfig>
 */
class NotetakerConfigFactory extends Factory
{
    protected $model = NotetakerConfig::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => $this->faker->firstName().' Bot',
            'avatar_path' => null,
            'intro_message' => null,
            'default_scope' => MeetingScope::Private->value,
        ];
    }
}
