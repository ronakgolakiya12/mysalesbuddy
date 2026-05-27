<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UnsupportedMeetingProviderException extends RuntimeException
{
    public function __construct(string $message = 'Only Google Meet URLs are supported at this time.')
    {
        parent::__construct($message);
    }
}
