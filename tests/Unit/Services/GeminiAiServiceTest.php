<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\TranscriptTooLargeException;
use App\Services\GeminiAiService;
use Gemini;
use Gemini\Data\GenerationConfig;
use RuntimeException;
use Tests\TestCase;

class GeminiAiServiceTest extends TestCase
{
    private string $capturedPrompt = '';

    private ?GenerationConfig $capturedConfig = null;

    protected function tearDown(): void
    {
        $this->capturedPrompt = '';
        $this->capturedConfig = null;
        parent::tearDown();
    }

    /**
     * Build a GeminiAiService that returns the given text from its internal
     * Gemini call, capturing prompt + config for assertions. We can't mock
     * `\Gemini\Client` (it's `final`), so we instantiate a real client (no
     * network call until `generateContent()`) and override the protected
     * `callGemini()` shim in an anonymous subclass.
     */
    private function makeServiceReturning(string $responseText): GeminiAiService
    {
        // Real client — constructor does no I/O.
        $client = Gemini::client('test-key');

        return new class ($client, $responseText, $this) extends GeminiAiService {
            public function __construct(
                \Gemini\Client $client,
                private readonly string $responseText,
                private readonly GeminiAiServiceTest $testCase,
            ) {
                parent::__construct($client);
            }

            protected function callGemini(string $model, GenerationConfig $config, string $prompt): string
            {
                $this->testCase->setCaptured($prompt, $config);

                return $this->responseText;
            }
        };
    }

    public function setCaptured(string $prompt, GenerationConfig $config): void
    {
        $this->capturedPrompt = $prompt;
        $this->capturedConfig = $config;
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
    // Sanitizer + extractor + prompt-rules coverage
    // ─────────────────────────────────────────────────────

    public function test_sanitize_removes_literal_newlines_in_strings(): void
    {
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
