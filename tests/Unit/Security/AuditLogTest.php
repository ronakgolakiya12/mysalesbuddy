<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Enums\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created(): void
    {
        $user = User::factory()->create();

        $log = AuditLog::create([
            'user_id' => $user->id,
            'event_type' => AuditEventType::MeetingCreated->value,
            'entity_type' => 'meeting',
            'entity_id' => (string) \Illuminate\Support\Str::uuid(),
            'metadata_json' => ['foo' => 'bar'],
        ]);

        $this->assertTrue($log->exists);
        $this->assertDatabaseHas('audit_log', ['id' => $log->id]);
    }

    public function test_audit_log_is_immutable_via_save(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'user_id' => $user->id,
            'event_type' => AuditEventType::MeetingCreated->value,
            'entity_type' => 'meeting',
            'entity_id' => (string) \Illuminate\Support\Str::uuid(),
            'metadata_json' => ['v' => 1],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AuditLog is immutable');

        $log->metadata_json = ['v' => 2];
        $log->save();
    }

    public function test_audit_log_is_immutable_via_update(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'user_id' => $user->id,
            'event_type' => AuditEventType::MeetingCreated->value,
            'entity_type' => 'meeting',
            'entity_id' => (string) \Illuminate\Support\Str::uuid(),
            'metadata_json' => ['v' => 1],
        ]);

        $this->expectException(\LogicException::class);
        $log->update(['metadata_json' => ['v' => 2]]);
    }
}
