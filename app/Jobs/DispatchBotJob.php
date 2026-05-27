<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DispatchBotAction;
use App\Events\MeetingStatusUpdated;
use App\Exceptions\DuplicateBotException;
use App\Exceptions\RecallApiException;
use App\Models\Meeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchBotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(public Meeting $meeting)
    {
        $this->onQueue('bots');
    }

    public function handle(DispatchBotAction $action): void
    {
        try {
            $action->execute($this->meeting);
        } catch (DuplicateBotException $e) {
            Log::warning('DispatchBotJob duplicate bot detected', [
                'meeting_id' => $this->meeting->id,
                'conflicting_meeting_id' => $e->conflictingMeetingId,
            ]);
            $this->fail($e);
        } catch (RecallApiException $e) {
            Log::warning('DispatchBotJob Recall API error', [
                'meeting_id' => $this->meeting->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->markFailed();
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    private function markFailed(): void
    {
        $this->meeting->refresh();
        $this->meeting->status = MeetingStatus::Failed;
        $this->meeting->save();
        broadcast(new MeetingStatusUpdated($this->meeting));
    }
}
