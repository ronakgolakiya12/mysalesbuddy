<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Support\Enums\CoachingMode;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CoachingAnalysisCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public CoachingAnalysis $analysis
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->meeting->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'CoachingAnalysisCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $mode = $this->analysis->mode;

        return [
            'meeting_id' => $this->meeting->id,
            'analysis_id' => $this->analysis->id,
            'overall_score' => $this->analysis->overall_score,
            'mode' => $mode instanceof CoachingMode ? $mode->value : (string) $mode,
        ];
    }
}
