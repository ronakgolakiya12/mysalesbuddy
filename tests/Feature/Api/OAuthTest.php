<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\OauthConnection;
use App\Models\User;
use App\Services\GoogleOAuthService;
use App\Support\Enums\OAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_redirect_returns_google_auth_url(): void
    {
        $user = User::factory()->create();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAuthorizationUrl')
                ->once()
                ->andReturn('https://accounts.google.com/o/oauth2/auth?state=xyz');
        });

        $this->actingAs($user);
        $response = $this->getJson('/api/auth/oauth/google/redirect');

        $response->assertStatus(200);
        $response->assertJsonPath('data.redirect_url', 'https://accounts.google.com/o/oauth2/auth?state=xyz');
    }

    public function test_redirect_requires_auth(): void
    {
        $response = $this->getJson('/api/auth/oauth/google/redirect');
        $response->assertStatus(401);
    }

    public function test_callback_with_valid_state_creates_connection(): void
    {
        $user = User::factory()->create();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exchangeCodeForTokens')
                ->once()
                ->with('auth-code-123')
                ->andReturn([
                    'access_token' => 'real-access',
                    'refresh_token' => 'real-refresh',
                    'expires_in' => 3600,
                    'scope' => 'openid email',
                ]);
        });

        $this->actingAs($user)
            ->withSession(['oauth_state' => 'state-xyz']);

        $response = $this->get('/api/oauth/google/callback?code=auth-code-123&state=state-xyz');

        $response->assertStatus(302);
        $response->assertRedirectContains('/settings/calendar?connected=google');
        $this->assertDatabaseHas('oauth_connections', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]);
    }

    public function test_callback_with_invalid_state_redirects_with_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['oauth_state' => 'expected']);

        $response = $this->get('/api/oauth/google/callback?code=abc&state=wrong');

        $response->assertStatus(302);
        $response->assertRedirectContains('error=invalid_state');
        $this->assertDatabaseMissing('oauth_connections', ['user_id' => $user->id]);
    }

    public function test_disconnect_deletes_connection(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]);

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('revokeToken')->once();
        });

        $this->actingAs($user);
        $response = $this->deleteJson('/api/auth/oauth/google');

        $response->assertStatus(204);
        $this->assertDatabaseMissing('oauth_connections', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]);
    }

    public function test_microsoft_oauth_endpoints_return_501(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/auth/oauth/microsoft/redirect')->assertStatus(501);
        $this->deleteJson('/api/auth/oauth/microsoft')->assertStatus(501);
    }
}
