<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppNotification>
 */
class AppNotificationFactory extends Factory
{
    protected $model = AppNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'coaching_ready',
            'payload_json' => [
                'meeting_id' => $this->faker->uuid(),
                'meeting_title' => $this->faker->sentence(3),
                'overall_score' => $this->faker->numberBetween(1, 10),
            ],
            'read_at' => null,
            'created_at' => now(),
        ];
    }

    public function unread(): self
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function read(): self
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function botBlocked(): self
    {
        return $this->state(fn () => [
            'type' => 'bot_blocked',
            'payload_json' => [
                'meeting_id' => $this->faker->uuid(),
                'meeting_title' => 'Blocked Meeting',
                'blocked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function pdfReady(): self
    {
        return $this->state(fn () => [
            'type' => 'pdf_ready',
            'payload_json' => [
                'meeting_id' => $this->faker->uuid(),
                'meeting_title' => 'Exported Meeting',
                'download_url' => 'https://example.com/exports/pdf.pdf',
            ],
        ]);
    }
}
