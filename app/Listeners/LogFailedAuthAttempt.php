<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class LogFailedAuthAttempt
{
    public function handle(Failed $event): void
    {
        $credentials = $event->credentials;
        $email = is_array($credentials) && isset($credentials['email']) && is_string($credentials['email'])
            ? $credentials['email']
            : null;

        Log::channel('security')->warning('auth.login_failed', [
            'email' => $email,
            'guard' => $event->guard,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
