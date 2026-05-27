<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AppNotification;
use Illuminate\Http\Request;

/**
 * @mixin AppNotification
 */
class AppNotificationResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AppNotification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'payload' => $notification->payload_json,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
