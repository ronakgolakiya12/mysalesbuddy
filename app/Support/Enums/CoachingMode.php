<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum CoachingMode: string
{
    case TranscriptOnly = 'transcript_only';
    case DiscoveryAware = 'discovery_aware';
}
