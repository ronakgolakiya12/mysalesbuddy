<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingProvider: string
{
    case GoogleMeet = 'google_meet';
    case Teams = 'teams';
    case Zoom = 'zoom';

    public function label(): string
    {
        return match ($this) {
            self::GoogleMeet => 'Google Meet',
            self::Teams => 'Microsoft Teams',
            self::Zoom => 'Zoom',
        };
    }
}
