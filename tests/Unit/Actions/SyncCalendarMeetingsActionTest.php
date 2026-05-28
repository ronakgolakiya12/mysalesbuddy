<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\SyncCalendarMeetingsAction;
use App\Exceptions\CalendarNotConnectedException;
use App\Exceptions\CalendarTokenExpiredException;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\OauthConnection;
use App\Models\User;
use App\Services\CalendarService;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncCalendarMeetingsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockCalendarService(array $events): void
    {
        $this->mock(CalendarService::class, function (MockInterface $m) use ($events) {
            $m->shouldReceive('getUpcomingGoogleEvents')
                ->andReturn($events);
        });
    }

    private function makeEvent(array $overrides = []): array
    {
        return array_merge([
            'id' => 'evt_'.uniqid(),
            'title' => 'Discovery call',
            'description' => null,
            'start_at' => CarbonImmutable::now()->addHours(2)->toIso8601String(),
            'end_at' => CarbonImmutable::now()->addHours(3)->toIso8601String(),
            'meeting_url' => 'https://meet.google.com/'.uniqid('abc-'),
            'provider' => MeetingProvider::GoogleMeet->value,
            'organiser_email' => 'host@example.com',
            'is_organiser' => true,
        ], $overrides);
    }

    public function test_throws_when_user_has_no_google_connection(): void
    {
        $user = User::factory()->create();

        $this->mock(CalendarService::class, function (MockInterface $m) {
            $m->shouldNotReceive('getUpcomingGoogleEvents');
        });

        $this->expectException(CalendarNotConnectedException::class);

        $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);
    }

    public function test_throws_when_token_expired(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->expired()->create(['user_id' => $user->id]);

        $this->mock(CalendarService::class, function (MockInterface $m) {
            $m->shouldNotReceive('getUpcomingGoogleEvents');
        });

        $this->expectException(CalendarTokenExpiredException::class);

        $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);
    }

    public function test_imports_new_google_meet_events_as_scheduled_meetings(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent(['title' => 'Demo with Acme', 'meeting_url' => 'https://meet.google.com/aaa-bbbb-ccc']),
            $this->makeEvent(['title' => 'QBR Globex', 'meeting_url' => 'https://meet.google.com/ddd-eeee-fff']),
        ]);

        $result = $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $this->assertCount(2, $result['imported']);
        $this->assertCount(0, $result['existing']);
        $this->assertCount(0, $result['skipped']);

        $this->assertDatabaseHas('meetings', [
            'user_id' => $user->id,
            'external_meeting_url' => 'https://meet.google.com/aaa-bbbb-ccc',
            'status' => MeetingStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas('meetings', [
            'user_id' => $user->id,
            'external_meeting_url' => 'https://meet.google.com/ddd-eeee-fff',
            'status' => MeetingStatus::Scheduled->value,
        ]);
    }

    public function test_marks_existing_meeting_when_active_meeting_already_exists_for_url(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $url = 'https://meet.google.com/already-scheduled';
        Meeting::factory()->create([
            'user_id' => $user->id,
            'external_meeting_url' => $url,
            'status' => MeetingStatus::Scheduled->value,
        ]);

        $this->mockCalendarService([
            $this->makeEvent(['title' => 'Dup', 'meeting_url' => $url]),
        ]);

        $result = $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $this->assertCount(0, $result['imported']);
        $this->assertCount(1, $result['existing']);
        $this->assertCount(0, $result['skipped']);
        $this->assertSame(1, Meeting::query()->where('external_meeting_url', $url)->count());
    }

    public function test_skips_events_without_a_meeting_url(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent(['meeting_url' => '', 'provider' => null]),
        ]);

        $result = $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $this->assertCount(0, $result['imported']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('missing_meeting_url', $result['skipped'][0]['reason']);
        $this->assertSame(0, Meeting::query()->count());
    }

    public function test_skips_events_for_unsupported_providers(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent([
                'meeting_url' => 'https://zoom.us/j/12345',
                'provider' => 'zoom',
            ]),
        ]);

        $result = $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $this->assertCount(0, $result['imported']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('unsupported_provider', $result['skipped'][0]['reason']);
    }

    public function test_skips_events_in_the_past(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent([
                'meeting_url' => 'https://meet.google.com/past-event-1',
                'start_at' => CarbonImmutable::now()->subHours(2)->toIso8601String(),
            ]),
        ]);

        $result = $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $this->assertCount(0, $result['imported']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('event_in_past', $result['skipped'][0]['reason']);
        $this->assertSame(0, Meeting::query()->count());
    }

    public function test_logs_calendar_synced_audit_event_with_counts(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent(['meeting_url' => 'https://meet.google.com/synced-evt-1']),
        ]);

        $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $audit = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'calendar.synced')
            ->first();

        $this->assertNotNull($audit);
        $metadata = $audit->metadata_json;
        $this->assertSame(1, $metadata['imported_count']);
        $this->assertSame(0, $metadata['existing_count']);
        $this->assertSame(0, $metadata['skipped_count']);
    }

    public function test_logs_scope_resolved_audit_event_per_imported_meeting(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent(['meeting_url' => 'https://meet.google.com/scope-evt-1']),
            $this->makeEvent(['meeting_url' => 'https://meet.google.com/scope-evt-2']),
            $this->makeEvent(['meeting_url' => 'https://meet.google.com/scope-evt-3']),
        ]);

        $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $count = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'scope.resolved')
            ->count();

        $this->assertSame(3, $count);
    }

    public function test_imported_meeting_has_private_scope_and_scheduled_status(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create(['user_id' => $user->id]);

        $this->mockCalendarService([
            $this->makeEvent([
                'meeting_url' => 'https://meet.google.com/scope-default',
                'title' => 'Scope test',
            ]),
        ]);

        $this->app->make(SyncCalendarMeetingsAction::class)->execute($user);

        $meeting = Meeting::query()
            ->where('external_meeting_url', 'https://meet.google.com/scope-default')
            ->first();

        $this->assertNotNull($meeting);
        $this->assertSame('private', $meeting->scope->value);
        $this->assertSame(MeetingStatus::Scheduled, $meeting->status);
        $this->assertSame(MeetingProvider::GoogleMeet, $meeting->provider);
        $this->assertSame('Scope test', $meeting->title);
    }
}
