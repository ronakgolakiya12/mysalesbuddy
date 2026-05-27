<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\OauthConnection;
use App\Models\User;
use App\Support\Enums\OAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_access_token_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $plaintext = 'super-secret-access-token-' . bin2hex(random_bytes(8));

        OauthConnection::create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::Google->value,
            'access_token' => $plaintext,
            'refresh_token' => 'refresh-' . bin2hex(random_bytes(8)),
            'token_expires_at' => now()->addHour(),
            'scopes' => ['calendar.readonly'],
        ]);

        $row = DB::table('oauth_connections')->where('user_id', $user->id)->first();
        $this->assertNotNull($row);

        // Raw row must not contain the plaintext token.
        $this->assertStringNotContainsString($plaintext, (string) $row->access_token);
        $this->assertStringNotContainsString($plaintext, (string) $row->refresh_token);

        // Reading back through the model must decrypt to the original value.
        $reloaded = OauthConnection::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($plaintext, $reloaded->access_token);
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-pass']);

        $this->assertNotSame('super-secret-pass', $user->getAuthPassword());
        $this->assertTrue(str_starts_with((string) $user->getAuthPassword(), '$2y$'));
    }
}
