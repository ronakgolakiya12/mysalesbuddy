<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DuplicateBotException extends RuntimeException
{
    public function __construct(
        public readonly string $conflictingMeetingId,
        string $message = 'A bot is already active for this meeting URL.'
    ) {
        parent::__construct($message);
    }
}
