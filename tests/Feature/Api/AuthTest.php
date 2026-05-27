<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CoachingPromptVersion;
use App\Models\NotetakerConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login|127.0.0.1');
        RateLimiter::clear('register|127.0.0.1');
    }

    public function test_register_creates_user_with_notetaker_config_and_prompt(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'has_google_calendar', 'has_microsoft_calendar', 'notetaker_config'],
        ]);
        $response->assertJsonPath('data.email', 'jane@example.com');
        $response->assertJsonPath('data.has_google_calendar', false);
        $response->assertJsonPath('data.has_microsoft_calendar', false);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNotNull($user->notetakerConfig);
        $this->assertSame("Jane's Assistant", $user->notetakerConfig->display_name);
        $this->assertTrue(CoachingPromptVersion::where('user_id', $user->id)->where('is_active', true)->exists());
    }

    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_authenticates_user_and_returns_data(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);
        NotetakerConfig::create([
            'user_id' => $user->id,
            'display_name' => "John's Assistant",
            'default_scope' => 'private',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'john@example.com');
        $response->assertJsonPath('data.notetaker_config.display_name', "John's Assistant");
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'fail@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'fail@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_returns_204_and_guests_user(): void
    {
        User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(204);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();
    }

    public function test_user_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        NotetakerConfig::create([
            'user_id' => $user->id,
            'display_name' => "Me's Assistant",
            'default_scope' => 'private',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'me@example.com');
        $response->assertJsonPath('data.has_google_calendar', false);
        $response->assertJsonPath('data.has_microsoft_calendar', false);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $response = $this->getJson('/api/nonexistent-endpoint');

        $response->assertStatus(404);
        $response->assertExactJson(['message' => 'API endpoint not found.']);
    }

    public function test_register_creates_default_notetaker_config_record(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Cathy Coach',
            'email' => 'cathy@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'cathy@example.com')->firstOrFail();
        $this->assertDatabaseHas('notetaker_configs', [
            'user_id' => $user->id,
            'display_name' => "Cathy's Assistant",
            'default_scope' => 'private',
        ]);
    }

    public function test_register_creates_default_active_coaching_prompt(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Patty Prompt',
            'email' => 'patty@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'patty@example.com')->firstOrFail();
        $prompt = CoachingPromptVersion::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($prompt);
        $this->assertNotEmpty($prompt->prompt_text);
    }

    public function test_register_rolls_back_when_creation_fails(): void
    {
        // Pre-create a user with the email so that the User insert fails
        // mid-transaction. Other side effects (notetaker config, prompt)
        // must not leak.
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Will Fail',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);

        $this->assertSame(0, NotetakerConfig::where('display_name', "Will's Assistant")->count());
        $this->assertSame(
            1,
            User::where('email', 'dup@example.com')->count(),
            'Only the original duplicate user should exist.'
        );
    }

    public function test_login_is_rate_limited(): void
    {
        // Use a unique IP address per-test to ensure no cross-test pollution
        // of the throttle cache. The login throttle is 20/min.
        $this->serverVariables = array_merge(
            $this->serverVariables ?? [],
            ['REMOTE_ADDR' => '198.51.100.42'],
        );

        User::factory()->create([
            'email' => 'rate@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Exhaust the 20-request budget.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'rate@example.com',
                'password' => 'wrong',
            ]);
        }

        // The 21st request should be throttled.
        $response = $this->postJson('/api/auth/login', [
            'email' => 'rate@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
    }
}
