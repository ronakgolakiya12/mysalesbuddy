<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TranscriptTooLargeException;
use App\Services\Ai\AiServiceInterface;
use Gemini\Client as GeminiClient;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiAiService implements AiServiceInterface
{
    private const MAX_TOKENS = 100000;

    public function __construct(private readonly GeminiClient $client)
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
                    $segment['speaker_label'],
                    $stamp,
                    $segment['body']
                );
            })
            ->implode("\n");

        $userMessage = "TRANSCRIPT:\n".$transcript;

        if ($dealContext !== null && trim($dealContext) !== '') {
            $userMessage .= "\n\nDEAL CONTEXT:\n".$dealContext;
        }

        // Gemini occasionally embeds raw newlines inside string values even with
        // responseMimeType=application/json. Tell it explicitly not to.
        $jsonInstruction = implode("\n", [
            'CRITICAL OUTPUT RULES:',
            '1. Return ONLY a valid JSON object. No markdown, no preamble.',
            '2. Do NOT use literal newline characters inside JSON string values.',
            '   Use \\n escape sequence if you need a line break in a string.',
            '3. Do NOT use literal tab characters inside JSON string values.',
            '   Use \\t escape sequence if needed.',
            '4. Ensure all string values use proper JSON escaping.',
            '5. The JSON must be parseable by PHP json_decode().',
        ]);

        // Gemini doesn't have separate "system" + "user" roles like OpenAI's
        // chat completions — we concatenate the coaching prompt, the output
        // rules, and the transcript into a single instruction.
        $fullPrompt = $prompt."\n\n".$jsonInstruction."\n\n".$userMessage;

        $model = (string) config('services.gemini.model', 'gemini-1.5-pro');

        $response = $this->client
            ->generativeModel($model)
            ->withGenerationConfig(new GenerationConfig(
                responseMimeType: ResponseMimeType::APPLICATION_JSON,
                temperature: 0.3,
                // The coaching JSON schema with 2-4 strengths + 2-4 opportunities
                // (each with title/detail/suggestion + evidence{speaker,timestamp_ms,quote})
                // can easily run past 2000 tokens and truncate mid-string. 8192 is the
                // documented max for Gemini 1.5 Pro and leaves comfortable headroom.
                maxOutputTokens: 8192,
            ))
            ->generateContent($fullPrompt);

        $rawContent = (string) $response->text();

        // Length-only telemetry: response_length near the 8192 token ceiling
        // (~7000 chars empirically) flags likely truncation for follow-up.
        Log::debug('Gemini raw response received', [
            'response_length' => strlen($rawContent),
            'possibly_truncated' => strlen($rawContent) >= 7000,
        ]);

        $jsonString = $this->extractJson($rawContent);
        $jsonString = $this->sanitizeJsonString($jsonString);

        /** @var mixed $decoded */
        $decoded = json_decode($jsonString, true);

        // Fallback: invalid UTF-8 byte sequences (rare with Gemini but cheap insurance)
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode($jsonString, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Gemini JSON decode failed after sanitization', [
                'json_error' => json_last_error_msg(),
                'sanitized_sample' => substr($jsonString, 0, 500),
            ]);

            throw new RuntimeException(
                'AI provider call failed: Gemini returned invalid JSON: '.
                json_last_error_msg().
                ' Raw response: '.substr($rawContent, 0, 200)
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned non-array JSON. Got: '.gettype($decoded));
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

    /**
     * Extract the JSON object from Gemini's response. Strips BOM, markdown
     * code fences, and any leading/trailing prose by anchoring on the first
     * `{` and last `}`. Throws if no object delimiters are present.
     */
    private function extractJson(string $raw): string
    {
        $cleaned = ltrim($raw, "\xEF\xBB\xBF");
        $cleaned = trim($cleaned);

        $cleaned = preg_replace('/^```json\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^```\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException(
                'Gemini response contains no JSON object. Raw: '.substr($cleaned, 0, 300)
            );
        }

        return substr($cleaned, $start, $end - $start + 1);
    }

    /**
     * Escape literal control characters that Gemini sometimes inserts inside
     * JSON string values (the "Control character error, possibly incorrectly
     * encoded" json_decode failure). The regex matches complete JSON string
     * tokens — including their existing escape sequences — so only string
     * INSIDES get rewritten. Structural whitespace between tokens is left
     * alone.
     */
    private function sanitizeJsonString(string $raw): string
    {
        $sanitized = preg_replace_callback(
            // Match a JSON string: "(any char except " or \) | (backslash + any char)*"
            '/"(?:[^"\\\\]|\\\\.)*"/s',
            static function (array $matches): string {
                $str = $matches[0];

                // Escape literal CR/LF/TAB inside the string value.
                $str = str_replace(
                    ["\r\n", "\r", "\n", "\t"],
                    ['\\n', '\\n', '\\n', '\\t'],
                    $str
                );

                // Strip other control chars (NUL through US, minus \t \n \r which
                // we already handled above) that have no valid JSON escape and
                // shouldn't appear in coaching prose anyway.
                $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str) ?? $str;

                return $str;
            },
            $raw
        );

        return $sanitized ?? $raw;
    }
}
