<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurgeSoftDeletedMeetingsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cutoff = now()->subDays(90);

        Meeting::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($meetings): void {
                foreach ($meetings as $meeting) {
                    try {
                        DB::transaction(function () use ($meeting): void {
                            $meeting->transcriptSegments()->delete();
                            $meeting->coachingAnalyses()->delete();
                            $meeting->forceDelete();
                        });

                        Log::info('meeting.purged', [
                            'meeting_id' => $meeting->id,
                            'deleted_at' => $meeting->deleted_at?->toIso8601String(),
                        ]);
                    } catch (Throwable $e) {
                        Log::error('meeting.purge_failed', [
                            'meeting_id' => $meeting->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
