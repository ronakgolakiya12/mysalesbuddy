<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_baseline_security_headers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/meetings');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-XSS-Protection', '0');
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }

    public function test_content_security_policy_includes_required_directives(): void
    {
        $response = $this->getJson('/api/meetings');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('connect-src', $csp);
        $this->assertStringContainsString('ws:', $csp);
        $this->assertStringContainsString('wss:', $csp);
    }

    public function test_hsts_header_only_set_on_https(): void
    {
        $response = $this->getJson('/api/meetings');

        // HTTP request → no HSTS header.
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
