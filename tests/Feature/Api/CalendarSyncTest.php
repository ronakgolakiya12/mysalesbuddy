<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\SyncCalendarMeetingsAction;
use App\Exceptions\CalendarNotConnectedException;
use App\Exceptions\CalendarTokenExpiredException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/calendar/sync')->assertStatus(401);
    }

    public function test_sync_returns_imported_existing_skipped_arrays(): void
    {
        $user = User::factory()->create();

        $payload = [
            'imported' => [
                [
                    'event_id' => 'evt_1',
                    'meeting_id' => 'meeting-uuid-1',
                    'title' => 'Demo',
                    'meeting_url' => 'https://meet.google.com/aaa',
                    'scheduled_at' => '2026-05-28T10:00:00+00:00',
                ],
            ],
            'existing' => [],
            'skipped' => [],
        ];

        $this->mock(SyncCalendarMeetingsAction::class, function (MockInterface $m) use ($payload, $user) {
            $m->shouldReceive('execute')
                ->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $user->id))
                ->andReturn($payload);
        });

        $this->actingAs($user)
            ->postJson('/api/calendar/sync')
            ->assertStatus(200)
            ->assertJsonPath('data.imported.0.meeting_id', 'meeting-uuid-1')
            ->assertJsonPath('data.existing', [])
            ->assertJsonPath('data.skipped', []);
    }

    public function test_returns_422_with_error_code_when_calendar_not_connected(): void
    {
        $user = User::factory()->create();

        $this->mock(SyncCalendarMeetingsAction::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andThrow(new CalendarNotConnectedException());
        });

        $this->actingAs($user)
            ->postJson('/api/calendar/sync')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'calendar_not_connected');
    }

    public function test_returns_422_with_error_code_when_token_expired(): void
    {
        $user = User::factory()->create();

        $this->mock(SyncCalendarMeetingsAction::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andThrow(new CalendarTokenExpiredException());
        });

        $this->actingAs($user)
            ->postJson('/api/calendar/sync')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'calendar_token_expired');
    }

    public function test_throttle_caps_requests_at_ten_per_minute(): void
    {
        $user = User::factory()->create();

        $this->mock(SyncCalendarMeetingsAction::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andReturn([
                'imported' => [],
                'existing' => [],
                'skipped' => [],
            ]);
        });

        // 10 successful requests, sharing a unique REMOTE_ADDR per test to avoid leakage.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
                ->postJson('/api/calendar/sync')
                ->assertStatus(200);
        }

        // 11th request must throttle.
        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->postJson('/api/calendar/sync')
            ->assertStatus(429);
    }

    public function test_legacy_events_endpoint_no_longer_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/calendar/events')
            ->assertStatus(404);
    }
}
