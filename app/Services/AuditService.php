<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Enums\AuditEventType;

class AuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        User $user,
        AuditEventType $event,
        string $entityType,
        ?string $entityId = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'event_type' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata,
        ]);
    }
}
