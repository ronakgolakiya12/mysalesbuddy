<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_with_modified_payload_returns_401(): void
    {
        Queue::fake();

        $signedBody = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_A']]) ?: '';
        $tamperedBody = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_HIJACK']]) ?: '';

        $headers = $this->validWebhookHeaders($signedBody);

        $response = $this->call(
            method: 'POST',
            uri: '/api/webhooks/recall',
            content: $tamperedBody,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ]
        );

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_webhook_with_no_signature_header_returns_401(): void
    {
        Queue::fake();

        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_X']]) ?: '';

        $response = $this->call(
            method: 'POST',
            uri: '/api/webhooks/recall',
            content: $body,
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_webhook_with_empty_signature_returns_401(): void
    {
        Queue::fake();

        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_X']]) ?: '';

        $response = $this->call(
            method: 'POST',
            uri: '/api/webhooks/recall',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => 'msg_test_empty',
                'HTTP_WEBHOOK_TIMESTAMP' => (string) time(),
                'HTTP_WEBHOOK_SIGNATURE' => '',
            ]
        );

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_webhook_with_old_timestamp_returns_401(): void
    {
        Queue::fake();

        $secretKey = 'dGVzdC1zZWNyZXQta2V5LWFiY2RlZmdoaWprbG1ub3A=';
        config(['services.recall.signing_secret' => 'whsec_' . $secretKey]);

        $body = json_encode(['event' => 'bot.in_call', 'data' => ['bot_id' => 'bot_X']]) ?: '';
        $id = 'msg_test_old';
        $oldTimestamp = (string) (time() - 600); // 10 minutes ago, beyond 5-min tolerance.
        $key = base64_decode($secretKey, true);
        $sig = 'v1,' . base64_encode(hash_hmac('sha256', "{$id}.{$oldTimestamp}.{$body}", $key, true));

        $response = $this->call(
            method: 'POST',
            uri: '/api/webhooks/recall',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $id,
                'HTTP_WEBHOOK_TIMESTAMP' => $oldTimestamp,
                'HTTP_WEBHOOK_SIGNATURE' => $sig,
            ]
        );

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    }
}
