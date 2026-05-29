<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiServiceFactory;
use App\Services\GeminiAiService;
use App\Services\OpenAiService;
use InvalidArgumentException;
use Tests\TestCase;

class AiServiceFactoryTest extends TestCase
{
    // The factory only resolves the service class. Instantiating the
    // underlying `\OpenAI\Client` / `\Gemini\Client` is harmless — neither
    // SDK touches the network until a request is actually issued — and
    // both classes are `final`, so Mockery::mock(...) on them is rejected.
    // We use the real container bindings instead of mocking.

    public function test_factory_returns_openai_service_when_configured(): void
    {
        config(['services.ai.provider' => 'openai']);

        $this->assertInstanceOf(OpenAiService::class, AiServiceFactory::make());
    }

    public function test_factory_returns_gemini_service_when_configured(): void
    {
        config(['services.ai.provider' => 'gemini']);

        $this->assertInstanceOf(GeminiAiService::class, AiServiceFactory::make());
    }

    public function test_factory_throws_on_unknown_provider(): void
    {
        config(['services.ai.provider' => 'anthropic']);

        $this->expectException(InvalidArgumentException::class);
        AiServiceFactory::make();
    }

    public function test_factory_defaults_to_openai_when_not_set(): void
    {
        config(['services.ai.provider' => null]);

        $this->assertInstanceOf(OpenAiService::class, AiServiceFactory::make());
    }
}
