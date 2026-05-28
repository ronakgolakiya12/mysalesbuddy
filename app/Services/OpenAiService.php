<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TranscriptTooLargeException;
use App\Services\Ai\AiServiceInterface;
use OpenAI\Client;
use RuntimeException;

class OpenAiService implements AiServiceInterface
{
    private const MAX_TOKENS = 100000;

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param  array<int, array{speaker_label: string, body: string, start_ms?: int|null}>  $segments
     * @return array<string, mixed>
     */
    public function analyzeTranscript(string $prompt, array $segments, ?string $dealContext = null): array
    {
        $transcript = collect($segments)
            ->map(static function (array $segment): string {
                $startMs = (int) ($segment['start_ms'] ?? 0);
                $stamp = gmdate('i:s', (int) floor($startMs / 1000));

                return sprintf(
                    '[%s @ %s] %s',
                    (string) ($segment['speaker_label'] ?? 'Unknown'),
                    $stamp,
                    (string) ($segment['body'] ?? '')
                );
            })
            ->implode("\n");

        $userMessage = "TRANSCRIPT:\n".$transcript;

        if ($dealContext !== null && trim($dealContext) !== '') {
            $userMessage .= "\n\nDEAL CONTEXT:\n".$dealContext;
        }

        $response = $this->client->chat()->create([
            'model' => (string) config('services.openai.model', 'gpt-4o'),
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';

        /** @var mixed $decoded */
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('OpenAI returned invalid JSON: '.json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned non-array JSON payload.');
        }

        return $decoded;
    }

    public function estimateTokens(string $text): int
    {
        $tokens = (int) ceil(str_word_count($text) * 1.35);

        if ($tokens > self::MAX_TOKENS) {
            throw new TranscriptTooLargeException(
                sprintf('Transcript exceeds maximum token budget (%d > %d).', $tokens, self::MAX_TOKENS)
            );
        }

        return $tokens;
    }
}
