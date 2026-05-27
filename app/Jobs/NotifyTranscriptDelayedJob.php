<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TranscriptDelayedMail;
use App\Models\Meeting;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyTranscriptDelayedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Meeting $meeting)
    {
        $this->onQueue('default');
    }

    public function handle(NotificationService $notifications): void
    {
        $meeting = $this->meeting->fresh();
        if ($meeting === null || $meeting->user === null) {
            return;
        }

        $notifications->notifyAndMail(
            $meeting->user,
            'transcript_delayed',
            [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->title,
                'ended_at' => $meeting->ended_at?->toIso8601String(),
            ],
            TranscriptDelayedMail::class
        );
    }
}
