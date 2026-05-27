<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum CoachingRating: string
{
    case Useful = 'useful';
    case NotUseful = 'not_useful';
}
