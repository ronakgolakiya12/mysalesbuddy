<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\GeminiAiService;
use App\Services\OpenAiService;
use App\Support\Enums\AiProvider;

class AiServiceFactory
{
    public static function make(): AiServiceInterface
    {
        return match (AiProvider::fromConfig()) {
            AiProvider::OpenAi => app(OpenAiService::class),
            AiProvider::Gemini => app(GeminiAiService::class),
        };
    }
}
