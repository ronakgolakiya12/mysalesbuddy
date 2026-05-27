<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return (string) $meeting->user_id === (string) $user->id;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return (string) $meeting->user_id === (string) $user->id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return (string) $meeting->user_id === (string) $user->id;
    }

    public function export(User $user, Meeting $meeting): bool
    {
        return (string) $meeting->user_id === (string) $user->id;
    }

    public function cancelDispatch(User $user, Meeting $meeting): bool
    {
        return (string) $meeting->user_id === (string) $user->id;
    }
}
