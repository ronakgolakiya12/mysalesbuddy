<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\DispatchBotAction;
use App\Events\MeetingStatusUpdated;
use App\Exceptions\DuplicateBotException;
use App\Models\Meeting;
use App\Models\NotetakerConfig;
use App\Models\User;
use App\Services\AuditService;
use App\Services\RecallAiService;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class DispatchBotActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_creates_bot_and_updates_meeting_to_bot_joining(): void
    {
        Event::fake();

        $user = User::factory()->create();
        NotetakerConfig::factory()->create(['user_id' => $user->id, 'display_name' => 'My Bot']);
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldReceive('createBot')
            ->once()
            ->with(Mockery::on(function ($payload) use ($meeting) {
                return $payload['meeting_url'] === $meeting->external_meeting_url
                    && $payload['bot_name'] === 'My Bot';
            }))
            ->andReturn(['id' => 'bot_xyz']);

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once();

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $action = $this->app->make(DispatchBotAction::class);
        $result = $action->execute($meeting);

        $this->assertSame('bot_xyz', $result->recall_bot_id);
        $this->assertSame(MeetingStatus::BotJoining, $result->status);
        Event::assertDispatched(MeetingStatusUpdated::class);
    }

    public function test_execute_is_idempotent_when_bot_already_dispatched(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_existing',
            'status' => MeetingStatus::Recording->value,
        ]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldNotReceive('createBot');

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldNotReceive('log');

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $action = $this->app->make(DispatchBotAction::class);
        $result = $action->execute($meeting);

        $this->assertSame('bot_existing', $result->recall_bot_id);
        $this->assertSame(MeetingStatus::Recording, $result->status);
    }

    public function test_execute_throws_duplicate_bot_when_another_active_meeting_uses_same_url(): void
    {
        $user = User::factory()->create();
        $url = 'https://meet.google.com/dup-test-1';

        Meeting::factory()->create([
            'user_id' => $user->id,
            'external_meeting_url' => $url,
            'recall_bot_id' => 'bot_active',
            'status' => MeetingStatus::Recording->value,
        ]);

        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'external_meeting_url' => $url,
            'status' => MeetingStatus::Scheduled->value,
        ]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldNotReceive('createBot');

        $audit = Mockery::mock(AuditService::class);

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $this->expectException(DuplicateBotException::class);

        $this->app->make(DispatchBotAction::class)->execute($meeting);
    }

    public function test_execute_uses_default_bot_name_when_notetaker_config_missing(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldReceive('createBot')
            ->once()
            ->with(Mockery::on(fn ($p) => $p['bot_name'] === 'Sales Buddy'))
            ->andReturn(['id' => 'bot_default']);

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once();

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $result = $this->app->make(DispatchBotAction::class)->execute($meeting);

        $this->assertSame('bot_default', $result->recall_bot_id);
    }

    public function test_execute_preserves_meeting_scope(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'scope' => \App\Support\Enums\MeetingScope::Team->value,
        ]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldReceive('createBot')->once()->andReturn(['id' => 'bot_scoped']);

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once();

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $result = $this->app->make(DispatchBotAction::class)->execute($meeting);

        $this->assertSame(\App\Support\Enums\MeetingScope::Team, $result->scope);
    }

    public function test_execute_sets_bot_joining_status_on_success(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldReceive('createBot')->once()->andReturn(['id' => 'bot_aa']);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once();

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $result = $this->app->make(DispatchBotAction::class)->execute($meeting);

        $this->assertSame(MeetingStatus::BotJoining, $result->status);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::BotJoining->value,
        ]);
    }

    public function test_execute_sets_recall_bot_id_from_response(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $recall = Mockery::mock(RecallAiService::class);
        $recall->shouldReceive('createBot')->once()->andReturn(['id' => 'bot_from_api_42']);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('log')->once();

        $this->app->instance(RecallAiService::class, $recall);
        $this->app->instance(AuditService::class, $audit);

        $result = $this->app->make(DispatchBotAction::class)->execute($meeting);

        $this->assertSame('bot_from_api_42', $result->recall_bot_id);
    }
}
