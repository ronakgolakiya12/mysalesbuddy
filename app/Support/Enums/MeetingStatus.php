<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingStatus: string
{
    case Scheduled = 'scheduled';
    case BotJoining = 'bot_joining';
    case Recording = 'recording';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Delayed = 'delayed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::BotJoining => 'Bot Joining',
            self::Recording => 'Recording',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Delayed => 'Delayed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Ready || $this === self::Failed;
    }
}
