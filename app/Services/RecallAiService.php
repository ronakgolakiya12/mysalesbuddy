<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RecallApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

class RecallAiService
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBot(array $payload): array
    {
        return $this->request('POST', 'bot', ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBot(string $botId): array
    {
        return $this->request('GET', "bot/{$botId}");
    }

    public function deleteBot(string $botId): void
    {
        try {
            $this->client->delete("bot/{$botId}");
        } catch (BadResponseException $e) {
            throw RecallApiException::fromResponse($e->getResponse(), $e);
        } catch (GuzzleException $e) {
            throw RecallApiException::fromThrowable($e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTranscript(string $botId): array
    {
        try {
            $response = $this->client->get("bot/{$botId}/transcript");
        } catch (BadResponseException $e) {
            throw RecallApiException::fromResponse($e->getResponse(), $e);
        } catch (GuzzleException $e) {
            throw RecallApiException::fromThrowable($e);
        }

        $body = (string) $response->getBody();
        /** @var array<int, array<string, mixed>>|null $decoded */
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Verify a Svix-delivered webhook (Recall.ai uses Svix).
     *
     * Headers expected:
     *   svix-id, svix-timestamp, svix-signature (space-separated list of "v1,<base64>")
     *
     * Algorithm: base64(hmac_sha256(key, "{id}.{timestamp}.{body}"))
     * where `key` is base64_decode(secret minus "whsec_" prefix).
     */
    public function verifyWebhookSignature(
        string $body,
        string $svixId,
        string $svixTimestamp,
        string $svixSignatureHeader,
        int $toleranceSeconds = 300
    ): bool {
        $secret = (string) config('services.recall.signing_secret');
        if ($secret === '' || $svixId === '' || $svixTimestamp === '' || $svixSignatureHeader === '') {
            return false;
        }

        if (! ctype_digit($svixTimestamp)) {
            return false;
        }
        $age = abs(time() - (int) $svixTimestamp);
        if ($age > $toleranceSeconds) {
            return false;
        }

        $secretBody = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $key = base64_decode($secretBody, true);
        if ($key === false || $key === '') {
            return false;
        }

        $signedPayload = "{$svixId}.{$svixTimestamp}.{$body}";
        $expected = base64_encode(hash_hmac('sha256', $signedPayload, $key, true));

        foreach (explode(' ', $svixSignatureHeader) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || ! str_starts_with($entry, 'v1,')) {
                continue;
            }
            $candidate = substr($entry, 3);
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (BadResponseException $e) {
            throw RecallApiException::fromResponse($e->getResponse(), $e);
        } catch (GuzzleException $e) {
            throw RecallApiException::fromThrowable($e);
        }

        $body = (string) $response->getBody();
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
