<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OauthConnection;
use App\Models\User;
use App\Support\Enums\OAuthProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

class OAuthConnectionService
{
    public function __construct(private readonly GoogleOAuthService $google)
    {
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    public function storeStateInSession(string $state): void
    {
        Session::put('oauth_state', $state);
    }

    public function validateState(?string $incomingState): bool
    {
        $stored = Session::get('oauth_state');
        Session::forget('oauth_state');

        if (! is_string($stored) || ! is_string($incomingState) || $stored === '' || $incomingState === '') {
            return false;
        }

        return hash_equals($stored, $incomingState);
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    public function upsertGoogleConnection(User $user, array $tokens): OauthConnection
    {
        $existing = OauthConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::Google->value)
            ->first();

        $refreshToken = isset($tokens['refresh_token']) && $tokens['refresh_token'] !== ''
            ? (string) $tokens['refresh_token']
            : ($existing?->refresh_token);

        $expiresAt = null;
        if (isset($tokens['expires_in']) && is_numeric($tokens['expires_in'])) {
            $expiresAt = now()->addSeconds((int) $tokens['expires_in']);
        }

        $scopes = [];
        if (isset($tokens['scope']) && is_string($tokens['scope'])) {
            $scopes = array_values(array_filter(explode(' ', $tokens['scope'])));
        }

        $attributes = [
            'access_token' => (string) ($tokens['access_token'] ?? ''),
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresAt,
            'scopes' => $scopes,
        ];

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->refresh();
        }

        return OauthConnection::create(array_merge($attributes, [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]));
    }

    public function disconnectGoogle(User $user): void
    {
        $connection = OauthConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::Google->value)
            ->first();

        if ($connection === null) {
            return;
        }

        try {
            $this->google->revokeToken($connection);
        } catch (Throwable $e) {
            Log::warning('Failed to revoke Google token during disconnect.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $connection->delete();
    }
}
