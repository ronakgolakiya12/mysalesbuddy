<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RecallApiException;
use App\Services\RecallAiService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class RecallAiServiceTest extends TestCase
{
    /**
     * @param  array<int, Response|\Throwable>  $queued
     */
    private function service(array $queued): RecallAiService
    {
        $mock = new MockHandler($queued);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack, 'base_uri' => 'https://api.recall.ai/api/v1/']);

        return new RecallAiService($client);
    }

    public function test_create_bot_returns_decoded_payload_on_success(): void
    {
        $service = $this->service([
            new Response(201, [], json_encode(['id' => 'bot_abc', 'status' => 'created']) ?: ''),
        ]);

        $result = $service->createBot([
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'bot_name' => 'Buddy',
        ]);

        $this->assertSame('bot_abc', $result['id']);
        $this->assertSame('created', $result['status']);
    }

    public function test_create_bot_rethrows_guzzle_errors_as_recall_api_exception(): void
    {
        $service = $this->service([
            new Response(422, [], json_encode(['error' => 'invalid url']) ?: ''),
        ]);

        $this->expectException(RecallApiException::class);

        $service->createBot(['meeting_url' => 'bad']);
    }

    public function test_verify_webhook_signature_returns_true_for_valid_svix_signature(): void
    {
        $secretKey = 'dGVzdC1zZWNyZXQta2V5LWFiY2RlZmdoaWprbG1ub3A=';
        config(['services.recall.signing_secret' => 'whsec_'.$secretKey]);

        $service = $this->service([]);

        $body = '{"event":"bot.in_call","data":{"bot_id":"bot_1"}}';
        $svixId = 'msg_test_abc123';
        $svixTimestamp = (string) time();
        $key = base64_decode($secretKey, true);
        $sig = 'v1,'.base64_encode(hash_hmac('sha256', "{$svixId}.{$svixTimestamp}.{$body}", $key, true));

        $this->assertTrue($service->verifyWebhookSignature($body, $svixId, $svixTimestamp, $sig));
    }

    public function test_verify_webhook_signature_rejects_invalid_signatures(): void
    {
        $secretKey = 'dGVzdC1zZWNyZXQta2V5LWFiY2RlZmdoaWprbG1ub3A=';
        config(['services.recall.signing_secret' => 'whsec_'.$secretKey]);

        $service = $this->service([]);

        $body = '{}';
        $svixId = 'msg_test_abc123';
        $svixTimestamp = (string) time();

        // Wrong signature value
        $this->assertFalse($service->verifyWebhookSignature($body, $svixId, $svixTimestamp, 'v1,deadbeef=='));

        // Empty header
        $this->assertFalse($service->verifyWebhookSignature($body, $svixId, $svixTimestamp, ''));

        // Missing headers
        $this->assertFalse($service->verifyWebhookSignature($body, '', $svixTimestamp, 'v1,xx'));
        $this->assertFalse($service->verifyWebhookSignature($body, $svixId, '', 'v1,xx'));

        // Stale timestamp (>5 min old)
        $key = base64_decode($secretKey, true);
        $oldTs = (string) (time() - 600);
        $oldSig = 'v1,'.base64_encode(hash_hmac('sha256', "{$svixId}.{$oldTs}.{$body}", $key, true));
        $this->assertFalse($service->verifyWebhookSignature($body, $svixId, $oldTs, $oldSig));
    }
}
