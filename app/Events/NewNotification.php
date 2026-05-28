<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public AppNotification $notification)
    {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'NewNotification';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'type' => $this->notification->type,
                'payload' => $this->notification->payload_json,
                // Explicit null (not omitted) so the SPA's strict equality
                // unread check (`read_at === null`) correctly counts it.
                'read_at' => $this->notification->read_at?->toIso8601String(),
                'created_at' => $this->notification->created_at?->toIso8601String(),
            ],
        ];
    }
}
