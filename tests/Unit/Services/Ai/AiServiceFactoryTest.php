<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiServiceFactory;
use App\Services\GeminiAiService;
use App\Services\OpenAiService;
use Gemini\Client as GeminiClient;
use InvalidArgumentException;
use Mockery;
use OpenAI\Client as OpenAiClient;
use Tests\TestCase;

class AiServiceFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stub the underlying SDK clients so the factory can resolve the
        // concrete service classes without making real API calls.
        $this->app->instance(OpenAiClient::class, Mockery::mock(OpenAiClient::class));
        $this->app->instance(GeminiClient::class, Mockery::mock(GeminiClient::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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
