<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_coaching_analysis_records_provider_used(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        CoachingAnalysis::factory()->completed()->create([
            'meeting_id' => $meeting->id,
            'provider_used' => 'openai',
        ]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(200)
            ->assertJsonPath('data.provider_used', 'openai');
    }

    public function test_coaching_analysis_provider_null_for_historical(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->ready()->create(['user_id' => $user->id]);
        CoachingAnalysis::factory()->completed()->create([
            'meeting_id' => $meeting->id,
            'provider_used' => null,
        ]);

        $this->actingAs($user)
            ->getJson("/api/meetings/{$meeting->id}/coaching")
            ->assertStatus(200)
            ->assertJsonPath('data.provider_used', null);
    }
}
