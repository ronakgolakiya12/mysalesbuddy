<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OauthConnection;
use App\Models\User;
use App\Support\Enums\OAuthProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OauthConnection>
 */
class OauthConnectionFactory extends Factory
{
    protected $model = OauthConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => OAuthProvider::Google->value,
            'access_token' => 'fake-access-token-'.$this->faker->uuid(),
            'refresh_token' => 'fake-refresh-token-'.$this->faker->uuid(),
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/calendar.readonly'],
        ];
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subMinutes(10),
        ]);
    }
}
