<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\NewNotification;
use App\Jobs\SendNotificationEmailJob;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notify(User $user, string $type, array $payload): AppNotification
    {
        /** @var AppNotification $notification */
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'payload_json' => $payload,
            'read_at' => null,
        ]);

        broadcast(new NewNotification($notification));

        return $notification;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string  $mailClass
     */
    public function notifyAndMail(User $user, string $type, array $payload, string $mailClass): AppNotification
    {
        $notification = $this->notify($user, $type, $payload);

        if ($user->prefersEmail($type)) {
            SendNotificationEmailJob::dispatch($user, $mailClass, $payload);
        }

        return $notification;
    }

    public function markRead(AppNotification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }

        $notification->markAsRead();
    }

    public function markAllRead(User $user): int
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * @return Collection<int, AppNotification>
     */
    public function getUnread(User $user): Collection
    {
        /** @var Collection<int, AppNotification> $items */
        $items = $user->notifications()
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $items;
    }
}
