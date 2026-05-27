<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoJoinScheduledMeetingsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(2);

        $meetings = Meeting::query()
            ->where('status', MeetingStatus::Scheduled->value)
            ->whereNull('recall_bot_id')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $windowEnd)
            ->where('scheduled_at', '>=', $now->copy()->subMinutes(5))
            ->get();

        foreach ($meetings as $meeting) {
            DispatchBotJob::dispatch($meeting);
        }
    }
}
