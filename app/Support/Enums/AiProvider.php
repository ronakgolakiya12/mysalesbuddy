<?php

declare(strict_types=1);

namespace App\Support\Enums;

use InvalidArgumentException;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Gemini = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI GPT-4o',
            self::Gemini => 'Google Gemini',
        };
    }

    public static function fromConfig(): self
    {
        /** @var mixed $raw */
        $raw = config('services.ai.provider', 'openai');

        // Treat null / empty as "not set" → default to OpenAI. Without this,
        // `config([...=> null])` would fall through to tryFrom('') and throw.
        if ($raw === null || $raw === '') {
            return self::OpenAi;
        }

        $value = (string) $raw;

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException(
                "Unknown AI provider: '{$value}'. Valid values: openai, gemini"
            );
    }
}
