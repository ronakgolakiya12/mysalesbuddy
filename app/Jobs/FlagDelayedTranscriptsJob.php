<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MeetingStatusUpdated;
use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FlagDelayedTranscriptsJob implements ShouldQueue
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
        $cutoff = now()->subMinutes(30);

        $stalled = Meeting::query()
            ->where('status', MeetingStatus::Processing->value)
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', $cutoff)
            ->get();

        foreach ($stalled as $meeting) {
            $meeting->status = MeetingStatus::Failed;
            $meeting->save();

            broadcast(new MeetingStatusUpdated($meeting));

            NotifyTranscriptDelayedJob::dispatch($meeting);

            Log::warning('meeting.transcript_delayed_flagged', [
                'meeting_id' => $meeting->id,
                'ended_at' => $meeting->ended_at?->toIso8601String(),
            ]);
        }
    }
}
