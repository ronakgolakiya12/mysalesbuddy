<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Events\MeetingStatusUpdated;
use App\Jobs\NotifyTranscriptFailedJob;
use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessTranscriptFailedJob implements ShouldQueue
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

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public string $botId, public array $context = [])
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

            if ($meeting->status === MeetingStatus::Failed) {
                return;
            }

            $meeting->status = MeetingStatus::Failed;
            $meeting->save();

            DB::afterCommit(function () use ($meeting): void {
                broadcast(new MeetingStatusUpdated($meeting));
                NotifyTranscriptFailedJob::dispatch($meeting);
            });
        });
    }
}
