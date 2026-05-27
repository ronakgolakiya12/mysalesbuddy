<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CoachingAnalysis;
use App\Models\User;

class CoachingAnalysisPolicy
{
    public function view(User $user, CoachingAnalysis $analysis): bool
    {
        $analysis->loadMissing('meeting');

        return $analysis->meeting !== null
            && (string) $analysis->meeting->user_id === (string) $user->id;
    }

    public function rate(User $user, CoachingAnalysis $analysis): bool
    {
        $analysis->loadMissing('meeting');

        return $analysis->meeting !== null
            && (string) $analysis->meeting->user_id === (string) $user->id;
    }
}
