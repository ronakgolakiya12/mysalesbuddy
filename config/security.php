<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Meeting URL Hosts
    |--------------------------------------------------------------------------
    |
    | Whitelist of hostnames the SSRF guard (AllowedMeetingUrl rule) will
    | accept when a user attempts to attach an external meeting URL. The
    | match is exact or suffix-based (e.g., "foo.meet.google.com" matches
    | "meet.google.com").
    |
    */

    'allowed_meeting_hosts' => [
        'meet.google.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Directives appended by App\Http\Middleware\SecurityHeaders. Production
    | deployments should replace 'unsafe-inline' with nonce-based directives
    | per the deployment runbook.
    |
    */

    'csp' => [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
        'style-src' => "'self' 'unsafe-inline' https://fonts.bunny.net",
        'font-src' => "'self' data: https://fonts.bunny.net",
        'img-src' => "'self' data: blob: https:",
        'connect-src' => "'self' ws: wss: https:",
        'frame-ancestors' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'object-src' => "'none'",
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Encryption
    |--------------------------------------------------------------------------
    |
    | Columns covered by the App\Casts\EncryptedString cast. Listed here for
    | documentation / audit only; the cast is wired up in the model.
    |
    */

    'encrypted_columns' => [
        'oauth_connections.access_token',
        'oauth_connections.refresh_token',
    ],

];
