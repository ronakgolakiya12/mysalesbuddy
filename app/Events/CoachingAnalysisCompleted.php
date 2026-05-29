<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\CoachingAnalysisResource;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
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
     * Broadcast the full analysis resource so the frontend can render the
     * completed coaching panel without needing a follow-up HTTP fetch. The
     * `meeting_id` stays at the top level so the channel listener can route
     * the event to the right page without parsing the nested analysis.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->analysis->loadMissing('ratings');

        return [
            'meeting_id' => $this->meeting->id,
            'analysis' => (new CoachingAnalysisResource($this->analysis))->resolve(),
        ];
    }
}
