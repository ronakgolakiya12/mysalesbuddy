<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Events\MeetingStatusUpdated;
use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessBotJoiningJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120, 300, 600];
    }

    public function __construct(public string $botId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $meeting = Meeting::query()
                ->where('recall_bot_id', $this->botId)
                ->lockForUpdate()
                ->first();

            if ($meeting === null) {
                return;
            }

            if ($meeting->status === MeetingStatus::BotJoining
                || $meeting->status === MeetingStatus::Recording
                || $meeting->status === MeetingStatus::Processing
                || $meeting->status === MeetingStatus::Ready
                || $meeting->status === MeetingStatus::Failed
            ) {
                return;
            }

            $meeting->status = MeetingStatus::BotJoining;
            $meeting->save();

            DB::afterCommit(fn () => broadcast(new MeetingStatusUpdated($meeting)));
        });
    }
}
