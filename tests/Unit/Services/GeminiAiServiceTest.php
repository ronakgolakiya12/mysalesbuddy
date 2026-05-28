<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\TranscriptTooLargeException;
use App\Services\GeminiAiService;
use Gemini\Client as GeminiClient;
use Gemini\Data\GenerationConfig;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GeminiAiServiceTest extends TestCase
{
    private string $capturedPrompt = '';

    private ?GenerationConfig $capturedConfig = null;

    protected function tearDown(): void
    {
        Mockery::close();
        $this->capturedPrompt = '';
        $this->capturedConfig = null;
        parent::tearDown();
    }

    /**
     * Build a GeminiAiService whose underlying client returns the given text
     * from generateContent, and capture both the prompt and the GenerationConfig.
     */
    private function makeServiceReturning(string $responseText): GeminiAiService
    {
        $response = Mockery::mock();
        $response->shouldReceive('text')->andReturn($responseText);

        $model = Mockery::mock();
        $model->shouldReceive('withGenerationConfig')
            ->andReturnUsing(function (GenerationConfig $config) use ($model) {
                $this->capturedConfig = $config;

                return $model;
            });
        $model->shouldReceive('generateContent')
            ->andReturnUsing(function (string $prompt) use ($response) {
                $this->capturedPrompt = $prompt;

                return $response;
            });

        /** @var GeminiClient&MockInterface $client */
        $client = Mockery::mock(GeminiClient::class);
        $client->shouldReceive('generativeModel')->andReturn($model);

        return new GeminiAiService($client);
    }

    /** @return array<int, array{speaker_label: string, body: string, start_ms: int}> */
    private function sampleSegments(): array
    {
        return [
            ['speaker_label' => 'Rep', 'body' => 'Hello and welcome.', 'start_ms' => 0],
            ['speaker_label' => 'Prospect', 'body' => 'Glad to be here.', 'start_ms' => 3000],
        ];
    }

    public function test_analyze_transcript_returns_decoded_array(): void
    {
        $service = $this->makeServiceReturning('{"overall_score": 8, "one_liner": "Solid call."}');

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertSame(8, $result['overall_score']);
    }

    public function test_analyze_transcript_passes_deal_context(): void
    {
        $service = $this->makeServiceReturning('{"overall_score": 7}');

        $service->analyzeTranscript('prompt', $this->sampleSegments(), 'Prospect: Acme Corp');

        $this->assertStringContainsString('DEAL CONTEXT:', $this->capturedPrompt);
        $this->assertStringContainsString('Acme Corp', $this->capturedPrompt);
    }

    public function test_analyze_transcript_omits_deal_context_when_null(): void
    {
        $service = $this->makeServiceReturning('{"overall_score": 7}');

        $service->analyzeTranscript('prompt', $this->sampleSegments(), null);

        $this->assertStringNotContainsString('DEAL CONTEXT:', $this->capturedPrompt);
    }

    public function test_analyze_transcript_strips_markdown_fences(): void
    {
        $service = $this->makeServiceReturning("```json\n{\"overall_score\":8}\n```");

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertSame(8, $result['overall_score']);
    }

    public function test_analyze_transcript_throws_on_invalid_json(): void
    {
        // Well-formed object delimiters but broken inside — survives extractJson(),
        // fails inside json_decode() even after sanitization.
        $service = $this->makeServiceReturning('{"score": broken}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini returned invalid JSON');

        $service->analyzeTranscript('prompt', $this->sampleSegments());
    }

    public function test_estimate_tokens_returns_integer(): void
    {
        $service = $this->makeServiceReturning('{}');

        $count = $service->estimateTokens('hello world');

        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function test_estimate_tokens_throws_for_oversized_text(): void
    {
        $service = $this->makeServiceReturning('{}');
        $hugeText = str_repeat('word ', 80000); // ~108k tokens

        $this->expectException(TranscriptTooLargeException::class);
        $service->estimateTokens($hugeText);
    }

    // ─────────────────────────────────────────────────────
    // New tests for the sanitizer + extractor + prompt rules
    // ─────────────────────────────────────────────────────

    public function test_sanitize_removes_literal_newlines_in_strings(): void
    {
        // Real newline character inside a string value — would normally trip
        // json_decode with "Control character error".
        $raw = "{\"summary\": \"first line\nsecond line\", \"score\": 8}";
        $service = $this->makeServiceReturning($raw);

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertSame(8, $result['score']);
        $this->assertStringContainsString('first line', $result['summary']);
        $this->assertStringContainsString('second line', $result['summary']);
    }

    public function test_sanitize_removes_literal_carriage_returns(): void
    {
        $raw = "{\"text\": \"line one\r\nline two\", \"score\": 7}";
        $service = $this->makeServiceReturning($raw);

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertSame(7, $result['score']);
    }

    public function test_extract_json_handles_markdown_fences(): void
    {
        $service = $this->makeServiceReturning("```json\n{\"score\": 9}\n```");

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertSame(9, $result['score']);
    }

    public function test_extract_json_handles_text_before_json(): void
    {
        $service = $this->makeServiceReturning("Here is the analysis:\n{\"score\": 6}");

        $result = $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertSame(6, $result['score']);
    }

    public function test_throws_when_no_json_object_found(): void
    {
        $service = $this->makeServiceReturning('I cannot analyse this transcript.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no JSON object');

        $service->analyzeTranscript('prompt', $this->sampleSegments());
    }

    public function test_handles_truncated_response_gracefully(): void
    {
        // No closing `}` — extractJson cannot recover, must throw cleanly.
        $service = $this->makeServiceReturning('{"overall_score": 4, "one_liner": "The rep');

        $this->expectException(RuntimeException::class);

        $service->analyzeTranscript('prompt', $this->sampleSegments());
    }

    public function test_uses_8192_max_output_tokens(): void
    {
        $service = $this->makeServiceReturning('{"score": 5}');

        $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertNotNull($this->capturedConfig);
        $this->assertSame(8192, $this->capturedConfig->maxOutputTokens);
    }

    public function test_prompt_includes_json_control_rules(): void
    {
        $service = $this->makeServiceReturning('{"score": 5}');

        $service->analyzeTranscript('prompt', $this->sampleSegments());

        $this->assertStringContainsString('CRITICAL OUTPUT RULES', $this->capturedPrompt);
        $this->assertStringContainsString('literal newline', $this->capturedPrompt);
    }
}
