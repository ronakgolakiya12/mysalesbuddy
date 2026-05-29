<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Enums;

use App\Support\Enums\AiProvider;
use InvalidArgumentException;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    public function test_from_config_returns_openai(): void
    {
        config(['services.ai.provider' => 'openai']);
        $this->assertSame(AiProvider::OpenAi, AiProvider::fromConfig());
    }

    public function test_from_config_returns_gemini(): void
    {
        config(['services.ai.provider' => 'gemini']);
        $this->assertSame(AiProvider::Gemini, AiProvider::fromConfig());
    }

    public function test_from_config_throws_on_invalid_value(): void
    {
        config(['services.ai.provider' => 'gpt5']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown AI provider: 'gpt5'");

        AiProvider::fromConfig();
    }

    public function test_from_config_defaults_to_openai_when_null(): void
    {
        config(['services.ai.provider' => null]);
        $this->assertSame(AiProvider::OpenAi, AiProvider::fromConfig());
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('OpenAI GPT-4o', AiProvider::OpenAi->label());
        $this->assertSame('Google Gemini', AiProvider::Gemini->label());
    }
}
