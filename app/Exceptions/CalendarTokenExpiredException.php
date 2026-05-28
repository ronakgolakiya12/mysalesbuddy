<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CalendarTokenExpiredException extends RuntimeException
{
    public function __construct(string $message = 'Google Calendar connection has expired. Please reconnect.')
    {
        parent::__construct($message);
    }
}
