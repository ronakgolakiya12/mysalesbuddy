<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CoachingReadyMail;
use App\Models\Meeting;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyCoachingReadyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Meeting $meeting, public ?int $score = null)
    {
        $this->onQueue('default');
    }

    public function handle(NotificationService $notifications): void
    {
        $meeting = $this->meeting->fresh();
        if ($meeting === null) {
            return;
        }

        $notifications->notifyAndMail(
            $meeting->user,
            'coaching_ready',
            [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->title,
                'overall_score' => $this->score,
            ],
            CoachingReadyMail::class
        );
    }
}
