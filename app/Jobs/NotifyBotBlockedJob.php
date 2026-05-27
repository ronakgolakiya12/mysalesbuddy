<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BotBlockedMail;
use App\Models\Meeting;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyBotBlockedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Meeting $meeting, public ?string $reason = null)
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
            'bot_blocked',
            [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->title,
                'reason' => $this->reason,
            ],
            BotBlockedMail::class
        );
    }
}
