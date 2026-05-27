<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OauthConnection;
use App\Services\GoogleOAuthService;
use Google\Client as GoogleClient;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_authorization_url_sets_state_and_returns_url(): void
    {
        /** @var GoogleClient&MockInterface $client */
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setState')->once()->with('the-state');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?state=the-state');

        $service = new GoogleOAuthService($client);

        $url = $service->getAuthorizationUrl('the-state');

        $this->assertSame('https://accounts.google.com/o/oauth2/auth?state=the-state', $url);
    }

    public function test_exchange_code_throws_on_google_error_response(): void
    {
        /** @var GoogleClient&MockInterface $client */
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('bad-code')
            ->andReturn(['error' => 'invalid_grant', 'error_description' => 'Bad code']);

        $service = new GoogleOAuthService($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad code');

        $service->exchangeCodeForTokens('bad-code');
    }

    public function test_refresh_access_token_throws_without_refresh_token(): void
    {
        /** @var GoogleClient&MockInterface $client */
        $client = Mockery::mock(GoogleClient::class);
        $service = new GoogleOAuthService($client);

        $connection = new OauthConnection();
        $connection->refresh_token = null;

        $this->expectException(RuntimeException::class);
        $service->refreshAccessToken($connection);
    }
}
