<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RecallApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

class RecallAiService
{
    public function __construct(private readonly Client $client) {}

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
     * Retrieve the transcript for a finished bot.
     *
     * Recall.ai's legacy `GET /bot/{id}/transcript` is deprecated. The current
     * flow is:
     *   1. GET /bot/{id}/  → bot record with recordings[]
     *   2. Each recording has media_shortcuts.transcript.data.download_url
     *      pointing to a pre-signed transcript JSON file.
     *   3. GET that URL (no Recall auth header — the URL is already signed).
     *
     * The downloaded payload is v2 shape (participant + words with nested
     * timestamps). This method normalises it to the legacy
     *   [{ speaker, words: [{ text, start_time, end_time }] }]
     * shape that ProcessTranscriptJob::parseSegments() consumes — so the
     * downstream pipeline doesn't have to change.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTranscript(string $botId): array
    {
        $bot = $this->getBot($botId);

        $downloadUrl = $this->extractTranscriptDownloadUrl($bot);
        if ($downloadUrl === null) {
            return [];
        }

        // Use a fresh client with NO default headers — the pre-signed S3 URL
        // already carries X-Amz-Algorithm + X-Amz-Signature query params, and
        // S3 rejects requests that also include an Authorization header
        // ("Only one auth mechanism allowed").
        $unauthenticated = new Client(['timeout' => 30]);

        try {
            $response = $unauthenticated->get($downloadUrl);
        } catch (BadResponseException $e) {
            throw RecallApiException::fromResponse($e->getResponse(), $e);
        } catch (GuzzleException $e) {
            throw RecallApiException::fromThrowable($e);
        }

        $body = (string) $response->getBody();
        /** @var array<int, array<string, mixed>>|null $decoded */
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $this->normaliseTranscript($decoded);
    }

    /**
     * Find a usable transcript download URL on a bot payload, scanning every
     * recording for a completed transcript.
     *
     * @param  array<string, mixed>  $bot
     */
    private function extractTranscriptDownloadUrl(array $bot): ?string
    {
        $recordings = $bot['recordings'] ?? [];
        if (! is_array($recordings)) {
            return null;
        }

        foreach ($recordings as $recording) {
            if (! is_array($recording)) {
                continue;
            }
            $url = data_get($recording, 'media_shortcuts.transcript.data.download_url');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Convert Recall.ai v2 transcript JSON to the legacy shape consumed by
     * ProcessTranscriptJob::parseSegments().
     *
     * v2 shape (input):
     *   [
     *     {
     *       "participant": { "id": 1, "name": "Otto" },
     *       "words": [
     *         { "text": "Hello", "start_timestamp": { "relative": 0.0, "absolute": "..." },
     *           "end_timestamp": { "relative": 0.5, "absolute": "..." } }
     *       ]
     *     }
     *   ]
     *
     * Legacy shape (output):
     *   [ { "speaker": "Otto", "words": [ { "text": "Hello", "start_time": 0.0, "end_time": 0.5 } ] } ]
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function normaliseTranscript(array $raw): array
    {
        $normalised = [];

        foreach ($raw as $entry) {
            // Already legacy shape — pass through.
            if (isset($entry['speaker']) && isset($entry['words'])) {
                $normalised[] = $entry;
                continue;
            }

            $speaker = (string) (data_get($entry, 'participant.name') ?? data_get($entry, 'speaker') ?? 'Unknown');
            $words = $entry['words'] ?? [];
            if (! is_array($words)) {
                continue;
            }

            $normalisedWords = [];
            foreach ($words as $word) {
                if (! is_array($word)) {
                    continue;
                }
                $text = (string) ($word['text'] ?? '');
                if ($text === '') {
                    continue;
                }

                $start = data_get($word, 'start_timestamp.relative')
                    ?? $word['start_time']
                    ?? 0;
                $end = data_get($word, 'end_timestamp.relative')
                    ?? $word['end_time']
                    ?? $start;

                $normalisedWords[] = [
                    'text' => $text,
                    'start_time' => (float) $start,
                    'end_time' => (float) $end,
                ];
            }

            if ($normalisedWords === []) {
                continue;
            }

            $normalised[] = [
                'speaker' => $speaker,
                'words' => $normalisedWords,
            ];
        }

        return $normalised;
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
