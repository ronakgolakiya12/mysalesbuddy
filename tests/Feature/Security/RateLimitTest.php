<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login|127.0.0.1');
        RateLimiter::clear('register|127.0.0.1');
    }

    private function withIp(string $ip): self
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this;
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        $ip = '203.0.113.10';

        for ($i = 0; $i < 20; $i++) {
            $this->withIp($ip)->postJson('/api/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->withIp($ip)->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too many requests. Try again later.']);
    }

    public function test_register_endpoint_is_rate_limited(): void
    {
        $ip = '203.0.113.11';

        // Send invalid registration payloads (missing fields) so each call returns
        // 422 without creating a user or starting a session. After 20 hits the
        // throttle middleware must reject with 429.
        for ($i = 0; $i < 20; $i++) {
            $this->withIp($ip)->postJson('/api/auth/register', [
                'email' => 'not-an-email',
            ]);
        }

        $response = $this->withIp($ip)->postJson('/api/auth/register', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(429);
    }

    public function test_avatar_upload_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $ip = '203.0.113.12';

        for ($i = 0; $i < 20; $i++) {
            $this->withIp($ip)->actingAs($user)->postJson('/api/settings/notetaker/avatar');
        }

        $response = $this->withIp($ip)->actingAs($user)->postJson('/api/settings/notetaker/avatar');

        $response->assertStatus(429);
    }

    public function test_prompt_store_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $ip = '203.0.113.13';

        for ($i = 0; $i < 20; $i++) {
            $this->withIp($ip)->actingAs($user)->postJson('/api/settings/prompt', [
                'prompt_text' => "Sample prompt body content number {$i} for rate limit testing.",
            ]);
        }

        $response = $this->withIp($ip)->actingAs($user)->postJson('/api/settings/prompt', [
            'prompt_text' => 'Sample prompt body content for final rate-limit attempt.',
        ]);

        $response->assertStatus(429);
    }

    public function test_oauth_google_redirect_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $ip = '203.0.113.14';

        for ($i = 0; $i < 10; $i++) {
            $this->withIp($ip)->actingAs($user)->getJson('/api/auth/oauth/google/redirect');
        }

        $response = $this->withIp($ip)->actingAs($user)->getJson('/api/auth/oauth/google/redirect');

        $response->assertStatus(429);
    }
}
