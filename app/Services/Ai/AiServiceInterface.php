<?php

declare(strict_types=1);

namespace App\Services\Ai;

interface AiServiceInterface
{
    /**
     * Analyse transcript segments and return structured coaching output
     * as an associative array.
     *
     * @param  string  $prompt  Active coaching prompt text.
     * @param  array<int, array{speaker_label: string, body: string, start_ms?: int|null}>  $segments
     * @param  string|null  $dealContext  Optional deal context for discovery-aware mode.
     * @return array<string, mixed>  Decoded JSON output from the AI model.
     *
     * @throws \RuntimeException  On API failure or invalid JSON response.
     */
    public function analyzeTranscript(string $prompt, array $segments, ?string $dealContext = null): array;

    /**
     * Estimate token count for a given text string. Used to enforce
     * transcript size limits before dispatch.
     *
     * @throws \App\Exceptions\TranscriptTooLargeException
     */
    public function estimateTokens(string $text): int;
}
