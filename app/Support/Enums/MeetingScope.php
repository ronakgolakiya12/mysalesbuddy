<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingScope: string
{
    case Private = 'private';
    case Team = 'team';
}
