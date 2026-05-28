<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Events\MeetingStatusUpdated;
use App\Jobs\NotifyBotBlockedJob;
use App\Jobs\ProcessTranscriptJob;
use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessBotLeftCallJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const BLOCKED_STATUS_CODES = [
        'meeting_not_started',
        'invalid_meeting_url',
        'meeting_ended_while_waiting',
        'participant_not_allowed',
    ];

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120, 300, 600];
    }

    public function __construct(public string $botId, public ?string $statusCode = null)
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

            // Idempotency: already processed or terminal.
            if ($meeting->status === MeetingStatus::Processing
                || $meeting->status === MeetingStatus::Ready
                || $meeting->status === MeetingStatus::Failed
            ) {
                return;
            }

            // Block path: bot never made it onto the call.
            if ($this->statusCode !== null && in_array($this->statusCode, self::BLOCKED_STATUS_CODES, true)) {
                $meeting->status = MeetingStatus::Failed;
                $meeting->ended_at = now();
                $meeting->save();

                $statusCode = $this->statusCode;

                DB::afterCommit(function () use ($meeting, $statusCode): void {
                    broadcast(new MeetingStatusUpdated($meeting));
                    NotifyBotBlockedJob::dispatch($meeting, $statusCode);
                });

                return;
            }

            $endedAt = now();
            $meeting->ended_at = $endedAt;
            if ($meeting->started_at !== null) {
                // Carbon 3 returns a SIGNED diff; when started_at < endedAt
                // the value is negative and max(0, ...) clamps it to zero.
                // abs() handles both directions defensively.
                $meeting->duration_seconds = (int) abs($endedAt->diffInSeconds($meeting->started_at));
            }
            $meeting->status = MeetingStatus::Processing;
            $meeting->save();

            DB::afterCommit(function () use ($meeting): void {
                broadcast(new MeetingStatusUpdated($meeting));
                ProcessTranscriptJob::dispatch($meeting)->delay(now()->addSeconds(30));
            });
        });
    }
}
