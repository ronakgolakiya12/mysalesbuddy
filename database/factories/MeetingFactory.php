<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\Enums\CoachingMode;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recall_bot_id' => null,
            'external_meeting_url' => 'https://meet.google.com/'.$this->faker->bothify('???-????-???'),
            'title' => $this->faker->sentence(3),
            'provider' => MeetingProvider::GoogleMeet->value,
            'status' => MeetingStatus::Scheduled->value,
            'scope' => MeetingScope::Private->value,
            'scheduled_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'duration_seconds' => null,
        ];
    }

    public function recording(): self
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Recording->value,
            'recall_bot_id' => 'bot_'.$this->faker->uuid(),
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function ready(): self
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Ready->value,
            'recall_bot_id' => 'bot_'.$this->faker->uuid(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
            'duration_seconds' => 1800,
        ]);
    }

    /**
     * Attach 10 transcript segments (alternating between two speakers)
     * once the meeting is created.
     */
    public function withTranscript(): self
    {
        return $this->afterCreating(function (Meeting $meeting): void {
            $speakers = ['Rep', 'Prospect'];
            $rows = [];
            $now = now();
            for ($i = 0; $i < 10; $i++) {
                $start = $i * 5_000;
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'meeting_id' => $meeting->id,
                    'speaker_label' => $speakers[$i % 2],
                    'body' => 'Segment '.($i + 1).': '.$this->faker->sentence(),
                    'start_ms' => $start,
                    'end_ms' => $start + 4_000,
                    'created_at' => $now,
                ];
            }
            TranscriptSegment::insert($rows);
        });
    }

    /**
     * Attach 10 transcript segments AND a completed coaching analysis
     * using output_json loaded from tests/Fixtures/coaching_output.json.
     */
    public function withCoaching(): self
    {
        return $this->withTranscript()->afterCreating(function (Meeting $meeting): void {
            $fixturePath = base_path('tests/Fixtures/coaching_output.json');
            $output = is_file($fixturePath)
                ? (array) (json_decode((string) file_get_contents($fixturePath), true) ?? [])
                : [];

            CoachingAnalysis::create([
                'meeting_id' => $meeting->id,
                'prompt_version_id' => null,
                'mode' => CoachingMode::TranscriptOnly,
                'overall_score' => (int) ($output['overall_score'] ?? 8),
                'talk_time_rep' => 55,
                'talk_time_prospect' => 45,
                'output_json' => $output,
                'triggered_by' => 'auto',
                'completed_at' => now(),
            ]);
        });
    }
}
