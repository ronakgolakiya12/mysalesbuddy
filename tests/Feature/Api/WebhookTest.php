<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\Webhooks\ProcessBotInCallJob;
use App\Jobs\Webhooks\ProcessBotJoiningJob;
use App\Jobs\Webhooks\ProcessBotLeftCallJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey = 'dGVzdC1zZWNyZXQta2V5LWFiY2RlZmdoaWprbG1ub3A='; // base64 of "test-secret-key-abcdefghijklmnop"

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.recall.signing_secret' => 'whsec_'.$this->secretKey]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}  [svix-id, svix-timestamp, svix-signature]
     */
    private function svixHeaders(string $body): array
    {
        $svixId = 'msg_test_'.bin2hex(random_bytes(6));
        $svixTimestamp = (string) time();
        $key = base64_decode($this->secretKey, true);
        $sig = 'v1,'.base64_encode(hash_hmac('sha256', "{$svixId}.{$svixTimestamp}.{$body}", $key, true));

        return [$svixId, $svixTimestamp, $sig];
    }

    private function callWebhook(string $body, ?array $headers): \Illuminate\Testing\TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($headers !== null) {
            [$svixId, $svixTimestamp, $svixSignature] = $headers;
            $server['HTTP_SVIX_ID'] = $svixId;
            $server['HTTP_SVIX_TIMESTAMP'] = $svixTimestamp;
            $server['HTTP_SVIX_SIGNATURE'] = $svixSignature;
        }

        return $this->call(
            method: 'POST',
            uri: '/api/webhooks/recall',
            content: $body,
            server: $server
        );
    }

    public function test_webhook_rejects_request_without_valid_signature(): void
    {
        Queue::fake();
        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_1']]) ?: '';

        $response = $this->callWebhook($body, ['msg_x', (string) time(), 'v1,invalidsignaturebase64==']);

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_webhook_rejects_request_with_missing_signature_header(): void
    {
        Queue::fake();
        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_1']]) ?: '';

        $response = $this->callWebhook($body, null);

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_webhook_dispatches_correct_job_for_in_call_event(): void
    {
        Queue::fake();
        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_42']]) ?: '';

        $response = $this->callWebhook($body, $this->svixHeaders($body));

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        Queue::assertPushed(ProcessBotInCallJob::class, fn ($job) => $job->botId === 'bot_42');
    }

    public function test_webhook_routes_other_events_to_their_jobs(): void
    {
        Queue::fake();

        $joining = json_encode(['event' => 'bot.joining_call', 'data' => ['bot_id' => 'bot_j']]) ?: '';
        $this->callWebhook($joining, $this->svixHeaders($joining))->assertStatus(200);

        $ended = json_encode(['event' => 'bot.call_ended', 'data' => ['bot_id' => 'bot_e']]) ?: '';
        $this->callWebhook($ended, $this->svixHeaders($ended))->assertStatus(200);

        Queue::assertPushed(ProcessBotJoiningJob::class, fn ($j) => $j->botId === 'bot_j');
        Queue::assertPushed(ProcessBotLeftCallJob::class, fn ($j) => $j->botId === 'bot_e');
    }

    public function test_webhook_left_call_with_block_status_routes_with_status_code(): void
    {
        Queue::fake();
        $user = \App\Models\User::factory()->create();
        $meeting = \App\Models\Meeting::factory()->create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_blocked',
            'status' => \App\Support\Enums\MeetingStatus::BotJoining->value,
        ]);

        $body = json_encode([
            'event' => 'bot.call_ended',
            'data' => ['bot_id' => 'bot_blocked', 'status_code' => 'meeting_not_started'],
        ]) ?: '';

        $this->callWebhook($body, $this->svixHeaders($body))->assertStatus(200);

        // The webhook dispatches the job with the right shape — the unit tests
        // for ProcessBotLeftCallJob cover the actual status transition.
        Queue::assertPushed(ProcessBotLeftCallJob::class, function (ProcessBotLeftCallJob $job) {
            return $job->botId === 'bot_blocked' && $job->statusCode === 'meeting_not_started';
        });

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'recall_bot_id' => 'bot_blocked',
        ]);
    }

    public function test_webhook_in_call_event_updates_meeting_status(): void
    {
        // Don't fake the queue: with QUEUE_CONNECTION=sync from phpunit.xml,
        // the job runs synchronously during the HTTP request and we can
        // observe the status transition end-to-end.
        $user = \App\Models\User::factory()->create();
        $meeting = \App\Models\Meeting::factory()->create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_42',
            'status' => \App\Support\Enums\MeetingStatus::BotJoining->value,
        ]);

        $body = json_encode([
            'event' => 'bot.in_call',
            'data' => ['bot_id' => 'bot_42'],
        ]) ?: '';

        $this->callWebhook($body, $this->svixHeaders($body))->assertStatus(200);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => \App\Support\Enums\MeetingStatus::Recording->value,
        ]);
    }

    public function test_webhook_is_idempotent_for_repeated_in_call_events(): void
    {
        $user = \App\Models\User::factory()->create();
        $meeting = \App\Models\Meeting::factory()->create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_idem',
            'status' => \App\Support\Enums\MeetingStatus::BotJoining->value,
        ]);

        $body = json_encode([
            'event' => 'bot.in_call',
            'data' => ['bot_id' => 'bot_idem'],
        ]) ?: '';

        $this->callWebhook($body, $this->svixHeaders($body))->assertStatus(200);
        $firstStartedAt = \App\Models\Meeting::find($meeting->id)?->started_at;

        // Replay the same event — started_at must not change.
        $this->callWebhook($body, $this->svixHeaders($body))->assertStatus(200);
        $secondStartedAt = \App\Models\Meeting::find($meeting->id)?->started_at;

        $this->assertNotNull($firstStartedAt);
        $this->assertSame(
            $firstStartedAt->toDateTimeString(),
            $secondStartedAt?->toDateTimeString()
        );
    }
}
