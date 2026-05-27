<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_returned_when_no_preferences_stored(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->assertTrue($user->prefersInApp('pdf_ready'));
        $this->assertTrue($user->prefersEmail('pdf_ready'));
        $this->assertFalse($user->prefersEmail('coaching_ready'));
    }

    public function test_stored_preferences_override_defaults(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'pdf_ready' => ['in_app' => false, 'email' => false],
                'coaching_ready' => ['in_app' => true, 'email' => true],
            ],
        ]);

        $this->assertFalse($user->prefersInApp('pdf_ready'));
        $this->assertFalse($user->prefersEmail('pdf_ready'));
        $this->assertTrue($user->prefersEmail('coaching_ready'));
    }

    public function test_unknown_type_falls_back_to_safe_default(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->assertTrue($user->prefersInApp('unknown_type'));
        $this->assertFalse($user->prefersEmail('unknown_type'));
    }
}
