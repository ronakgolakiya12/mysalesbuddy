<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum OAuthProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
}
