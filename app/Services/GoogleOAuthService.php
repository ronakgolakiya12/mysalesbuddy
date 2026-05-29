<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OauthConnection;
use Google\Client as GoogleClient;
use RuntimeException;

class GoogleOAuthService
{
    private GoogleClient $client;

    public function __construct(?GoogleClient $client = null)
    {
        // Honour the injected client (used by tests / DI overrides). Only build
        // a default when no client is provided — otherwise the constructor
        // also leaks Google\Client's error handlers, which PHPUnit flags as
        // risky.
        $this->client = $client ?? $this->buildDefaultClient();
    }

    private function buildDefaultClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));

        foreach ((array) config('google.scopes', []) as $scope) {
            $client->addScope((string) $scope);
        }

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    public function getAuthorizationUrl(string $state): string
    {
        $this->client->setState($state);

        return (string) $this->client->createAuthUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $tokens = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($tokens['error'])) {
            $message = isset($tokens['error_description'])
                ? (string) $tokens['error_description']
                : 'Failed to exchange authorization code for tokens.';

            throw new RuntimeException($message);
        }

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshAccessToken(OauthConnection $connection): array
    {
        if ($connection->refresh_token === null || $connection->refresh_token === '') {
            throw new RuntimeException('No refresh token available for connection.');
        }

        $this->client->setClientId((string) config('google.client_id'));
        $this->client->setClientSecret((string) config('google.client_secret'));

        $tokens = $this->client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

        if (isset($tokens['error'])) {
            $message = isset($tokens['error_description'])
                ? (string) $tokens['error_description']
                : 'Failed to refresh access token.';

            throw new RuntimeException($message);
        }

        return $tokens;
    }

    public function revokeToken(OauthConnection $connection): void
    {
        $token = $connection->access_token;

        if ($token === '') {
            return;
        }

        $result = $this->client->revokeToken($token);

        if ($result === false) {
            throw new RuntimeException('Failed to revoke Google token.');
        }
    }

    public function buildAuthorisedClient(OauthConnection $connection): GoogleClient
    {
        $token = [
            'access_token' => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
        ];

        if ($connection->token_expires_at !== null) {
            $token['expires_in'] = max(0, $connection->token_expires_at->diffInSeconds(now(), false) * -1);
            $token['created'] = $connection->token_expires_at->getTimestamp() - (int) ($token['expires_in']);
        }

        $this->client->setAccessToken($token);

        return $this->client;
    }
}
