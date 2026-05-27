<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use App\Services\OAuthConnectionService;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OAuthController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly GoogleOAuthService $google,
        private readonly OAuthConnectionService $connections,
    ) {
    }

    public function redirectToGoogle(Request $request): JsonResponse
    {
        unset($request);

        $state = $this->connections->generateState();
        $this->connections->storeStateInSession($state);

        $url = $this->google->getAuthorizationUrl($state);

        return $this->success(['redirect_url' => $url]);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($request->query('error') !== null) {
            return redirect()->away($base.'/settings/calendar?error='.urlencode((string) $request->query('error')));
        }

        $state = $request->query('state');
        $code = $request->query('code');

        if (! $this->connections->validateState(is_string($state) ? $state : null)) {
            return redirect()->away($base.'/settings/calendar?error=invalid_state');
        }

        if (! is_string($code) || $code === '') {
            return redirect()->away($base.'/settings/calendar?error=missing_code');
        }

        try {
            $tokens = $this->google->exchangeCodeForTokens($code);

            $user = $request->user();
            if ($user === null) {
                return redirect()->away($base.'/login');
            }

            $this->connections->upsertGoogleConnection($user, $tokens);
        } catch (Throwable $e) {
            Log::error('Google OAuth callback failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->away($base.'/settings/calendar?error=oauth_failed');
        }

        return redirect()->away($base.'/settings/calendar?connected=google');
    }

    public function disconnectGoogle(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $this->connections->disconnectGoogle($user);

        return $this->noContent();
    }
}
