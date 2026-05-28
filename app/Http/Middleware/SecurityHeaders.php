<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Vite dev server origins (only relaxed in non-production environments
     * to allow HMR + module loading from the dev server).
     */
    private const VITE_DEV_ORIGINS = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ];

    private const VITE_DEV_WS_ORIGINS = [
        'ws://localhost:5173',
        'ws://127.0.0.1:5173',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-XSS-Protection', '0');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    private function buildCsp(): string
    {
        /** @var array<string, string> $directives */
        $directives = (array) config('security.csp', []);

        if (! app()->environment('production')) {
            $directives = $this->relaxForViteDev($directives);
        }

        $parts = [];
        foreach ($directives as $name => $value) {
            $parts[] = trim($name) . ' ' . trim((string) $value);
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, string>  $directives
     * @return array<string, string>
     */
    private function relaxForViteDev(array $directives): array
    {
        $extraScript = ' ' . implode(' ', self::VITE_DEV_ORIGINS);
        $extraConnect = ' ' . implode(' ', array_merge(self::VITE_DEV_ORIGINS, self::VITE_DEV_WS_ORIGINS));

        $directives['script-src'] = ($directives['script-src'] ?? "'self'") . $extraScript;
        $directives['connect-src'] = ($directives['connect-src'] ?? "'self'") . $extraConnect;
        $directives['style-src'] = ($directives['style-src'] ?? "'self'") . $extraScript;

        return $directives;
    }
}
