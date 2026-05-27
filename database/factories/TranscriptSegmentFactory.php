<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\TranscriptSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptSegment>
 */
class TranscriptSegmentFactory extends Factory
{
    protected $model = TranscriptSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startMs = $this->faker->numberBetween(0, 600_000);

        return [
            'meeting_id' => Meeting::factory(),
            'speaker_label' => $this->faker->randomElement(['Rep', 'Prospect']),
            'body' => $this->faker->sentence(),
            'start_ms' => $startMs,
            'end_ms' => $startMs + $this->faker->numberBetween(1_000, 5_000),
            'created_at' => now(),
        ];
    }
}
