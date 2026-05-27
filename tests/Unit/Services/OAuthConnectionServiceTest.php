<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OauthConnection;
use App\Models\User;
use App\Services\GoogleOAuthService;
use App\Services\OAuthConnectionService;
use App\Support\Enums\OAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

class OAuthConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(?GoogleOAuthService $google = null): OAuthConnectionService
    {
        return new OAuthConnectionService($google ?? Mockery::mock(GoogleOAuthService::class));
    }

    public function test_validate_state_succeeds_with_matching_session_value(): void
    {
        $service = $this->service();
        Session::put('oauth_state', 'abc123');

        $this->assertTrue($service->validateState('abc123'));
        $this->assertNull(Session::get('oauth_state'));
    }

    public function test_validate_state_fails_when_session_missing_or_mismatch(): void
    {
        $service = $this->service();

        $this->assertFalse($service->validateState('something'));

        Session::put('oauth_state', 'abc123');
        $this->assertFalse($service->validateState('different'));
        $this->assertNull(Session::get('oauth_state'));
    }

    public function test_upsert_preserves_existing_refresh_token_when_google_omits_it(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
            'refresh_token' => 'existing-refresh',
        ]);

        $service = $this->service();
        $service->upsertGoogleConnection($user, [
            'access_token' => 'new-access',
            'expires_in' => 3600,
            'scope' => 'openid email',
        ]);

        $fresh = OauthConnection::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('new-access', $fresh->access_token);
        $this->assertSame('existing-refresh', $fresh->refresh_token);
    }

    public function test_disconnect_google_revokes_and_deletes_connection(): void
    {
        $user = User::factory()->create();
        OauthConnection::factory()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]);

        $google = Mockery::mock(GoogleOAuthService::class);
        $google->shouldReceive('revokeToken')->once();

        $service = $this->service($google);
        $service->disconnectGoogle($user);

        $this->assertDatabaseMissing('oauth_connections', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
        ]);
    }
}
