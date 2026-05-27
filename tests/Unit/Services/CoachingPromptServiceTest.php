<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CoachingPromptVersion;
use App\Models\User;
use App\Services\CoachingPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CoachingPromptServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoachingPromptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CoachingPromptService::class);
    }

    public function test_get_active_prompt_throws_when_none(): void
    {
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service->getActivePrompt($user);
    }

    public function test_create_version_deactivates_previous(): void
    {
        $user = User::factory()->create();
        $first = $this->service->createVersion($user, 'Version one prompt text.');
        $second = $this->service->createVersion($user, 'Version two prompt text.');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);

        $active = $this->service->getActivePrompt($user);
        $this->assertSame($second->id, $active->id);
    }

    public function test_get_version_history_returns_latest_first(): void
    {
        $user = User::factory()->create();

        $v1 = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
            'created_at' => now()->subDays(3),
        ]);
        $v2 = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
            'created_at' => now()->subDays(2),
        ]);
        $v3 = CoachingPromptVersion::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now()->subDay(),
        ]);

        $history = $this->service->getVersionHistory($user);

        $this->assertCount(3, $history);
        $this->assertSame($v3->id, $history[0]->id);
        $this->assertSame($v2->id, $history[1]->id);
        $this->assertSame($v1->id, $history[2]->id);
    }

    public function test_restore_version_creates_new_active_copy(): void
    {
        $user = User::factory()->create();
        $original = $this->service->createVersion($user, 'Original prompt text content.');
        $this->service->createVersion($user, 'Newer prompt text content.');

        $restored = $this->service->restoreVersion($user, $original);

        $this->assertNotSame($original->id, $restored->id);
        $this->assertSame('Original prompt text content.', $restored->prompt_text);
        $this->assertTrue($restored->fresh()->is_active);
        $this->assertSame($restored->id, $this->service->getActivePrompt($user)->id);
    }

    public function test_restore_version_rejects_foreign_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $version = CoachingPromptVersion::factory()->create(['user_id' => $owner->id]);

        $this->expectException(RuntimeException::class);
        $this->service->restoreVersion($intruder, $version);
    }
}
