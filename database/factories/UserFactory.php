<?php

namespace Database\Factories;

use App\Models\OauthConnection;
use App\Models\User;
use App\Support\Enums\OAuthProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Attach a Google calendar OAuth connection (expires in 1h)
     * after the user is created.
     */
    public function withGoogleCalendar(): self
    {
        return $this->afterCreating(function (User $user): void {
            OauthConnection::create([
                'user_id' => $user->id,
                'provider' => OAuthProvider::Google->value,
                'access_token' => 'access-'.Str::random(20),
                'refresh_token' => 'refresh-'.Str::random(20),
                'token_expires_at' => now()->addHour(),
                'scopes' => ['https://www.googleapis.com/auth/calendar.readonly'],
            ]);
        });
    }
}
