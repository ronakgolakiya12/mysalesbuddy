<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CalendarNotConnectedException extends RuntimeException
{
    public function __construct(string $message = 'Google Calendar is not connected.')
    {
        parent::__construct($message);
    }
}
