<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\TriggerCoachingRequest;
use App\Http\Resources\CoachingAnalysisResource;
use App\Http\Resources\CoachingRatingResource;
use App\Jobs\CoachingAnalysisJob;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Services\AuditService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\CoachingRating as CoachingRatingEnum;
use App\Support\Enums\MeetingStatus;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoachingController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $analysis = $meeting->coachingAnalyses()
            ->with('ratings')
            ->orderByDesc('created_at')
            ->first();

        if ($analysis === null) {
            return $this->error('No coaching analysis found for this meeting.', 404);
        }

        return $this->successResource(new CoachingAnalysisResource($analysis));
    }

    public function trigger(TriggerCoachingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        if ($meeting->status !== MeetingStatus::Ready) {
            return $this->error('Meeting is not ready for coaching analysis.', 409);
        }

        $mode = $request->modeValue();
        $dealContext = $request->dealContext();

        $meeting->loadMissing('user');

        $analysis = DB::transaction(function () use ($meeting, $mode, $dealContext): CoachingAnalysis {
            $analysis = CoachingAnalysis::create([
                'meeting_id' => $meeting->id,
                'prompt_version_id' => null,
                'mode' => $mode,
                'deal_context' => $dealContext,
                'triggered_by' => 'manual',
            ]);

            $this->audit->log(
                user: $meeting->user,
                event: AuditEventType::CoachingTriggered,
                entityType: 'coaching_analysis',
                entityId: (string) $analysis->id,
                metadata: [
                    'triggered_by' => 'manual',
                    'mode' => $mode->value,
                ]
            );

            return $analysis;
        });

        CoachingAnalysisJob::dispatch($meeting, $analysis->id, $mode->value, $dealContext);

        // Return the full resource (with `status` derived as 'pending' from
        // null completed_at/failed_at) so the frontend can render the pending
        // spinner immediately. The job's later CoachingAnalysisCompleted broadcast
        // refreshes the panel with the real output.
        $analysis->load('ratings');

        return $this->successResource(new CoachingAnalysisResource($analysis))
            ->setStatusCode(202);
    }

    public function rateItem(Request $request, CoachingAnalysis $analysis): JsonResponse
    {
        $this->authorize('rate', $analysis);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'section_key' => ['required', 'string', 'max:50'],
            'rating' => ['required', Rule::in([
                CoachingRatingEnum::Useful->value,
                CoachingRatingEnum::NotUseful->value,
            ])],
        ]);

        $rating = $analysis->ratings()->updateOrCreate(
            ['section_key' => $validated['section_key']],
            ['rating' => $validated['rating']]
        );

        $this->audit->log(
            user: $user,
            event: AuditEventType::CoachingRated,
            entityType: 'coaching_rating',
            entityId: (string) $rating->id,
            metadata: [
                'coaching_analysis_id' => $analysis->id,
                'section_key' => $validated['section_key'],
                'rating' => $validated['rating'],
            ]
        );

        return $this->successResource(new CoachingRatingResource($rating));
    }
}
