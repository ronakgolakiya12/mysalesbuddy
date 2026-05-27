<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppNotification;
use App\Models\User;

class AppNotificationPolicy
{
    public function view(User $user, AppNotification $notification): bool
    {
        return (string) $notification->user_id === (string) $user->id;
    }

    public function update(User $user, AppNotification $notification): bool
    {
        return (string) $notification->user_id === (string) $user->id;
    }
}
