<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Resources\MeetingListResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\TranscriptSegmentResource;
use App\Jobs\DispatchBotJob;
use App\Jobs\GeneratePdfExportJob;
use App\Models\Meeting;
use App\Services\AuditService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\MeetingStatus;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $query = Meeting::query()->where('user_id', $user->id);

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = (string) $request->query('search', '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                    ->orWhere('external_meeting_url', 'ilike', '%' . $search . '%');
            });
        }

        $from = (string) $request->query('from', '');
        if ($from !== '') {
            $query->where('created_at', '>=', $from);
        }

        $to = (string) $request->query('to', '');
        if ($to !== '') {
            $query->where('created_at', '<=', $to);
        }

        $query->orderByDesc('created_at');

        $perPage = 20;
        $paginator = $query->paginate($perPage);

        return $this->paginated($paginator, MeetingListResource::class);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load(['transcriptSegments', 'latestCoachingAnalysis']);

        return $this->successResource(new MeetingResource($meeting));
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $provider = $request->detectProvider();
        $scope = $request->scopeValue();

        $scheduledAt = $request->input('scheduled_at');

        $meeting = DB::transaction(function () use ($request, $user, $provider, $scope, $scheduledAt) {
            $meeting = new Meeting([
                'user_id' => $user->id,
                'external_meeting_url' => (string) $request->input('external_meeting_url'),
                'title' => $request->input('title'),
                'provider' => $provider,
                'status' => MeetingStatus::Scheduled,
                'scope' => $scope,
                'scheduled_at' => $scheduledAt,
            ]);
            $meeting->save();

            $this->audit->log(
                user: $user,
                event: AuditEventType::MeetingCreated,
                entityType: 'meeting',
                entityId: (string) $meeting->id,
                metadata: [
                    'provider' => $provider->value,
                    'scheduled_at' => $scheduledAt,
                ]
            );

            return $meeting;
        });

        if ($scheduledAt === null || $scheduledAt === '') {
            DispatchBotJob::dispatch($meeting);
        }

        return $this->successResource(new MeetingResource($meeting))->setStatusCode(201);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        $activeStatuses = [
            MeetingStatus::BotJoining,
            MeetingStatus::Recording,
            MeetingStatus::Processing,
        ];

        if (in_array($meeting->status, $activeStatuses, true)) {
            return $this->error('Cannot delete a meeting while the bot is active.', 409);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $meetingId = (string) $meeting->id;
        $meeting->delete();

        $this->audit->log(
            user: $user,
            event: AuditEventType::MeetingDeleted,
            entityType: 'meeting',
            entityId: $meetingId
        );

        return $this->noContent();
    }

    public function cancelDispatch(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('cancelDispatch', $meeting);

        if ($meeting->status !== MeetingStatus::Scheduled) {
            return $this->error('Only scheduled meetings can be cancelled.', 422);
        }

        $createdAt = $meeting->created_at;
        if ($createdAt !== null && $createdAt->diffInSeconds(now()) > 30) {
            return $this->error('Cancellation window has expired (30 seconds).', 422);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $meeting->status = MeetingStatus::Cancelled;
        $meeting->save();

        $this->audit->log(
            user: $user,
            event: AuditEventType::MeetingDispatchCancelled,
            entityType: 'meeting',
            entityId: (string) $meeting->id
        );

        return $this->successResource(new MeetingResource($meeting));
    }

    public function transcript(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        if ($meeting->status !== MeetingStatus::Ready) {
            return $this->error('Transcript not yet available.', 409);
        }

        $search = trim((string) $request->query('search', ''));

        $segmentsQuery = $meeting->transcriptSegments()->orderBy('start_ms');

        if ($search !== '') {
            $segmentsQuery->search($search);
        }

        $segments = $segmentsQuery->get();
        $analysis = $meeting->latestCoachingAnalysis()->first();

        return $this->success([
            'segments' => TranscriptSegmentResource::collection($segments),
            'talk_time_rep' => $analysis?->talk_time_rep,
            'talk_time_prospect' => $analysis?->talk_time_prospect,
            'search' => $search !== '' ? $search : null,
            'total_segments' => $meeting->transcriptSegments()->count(),
            'match_count' => $search !== '' ? $segments->count() : null,
        ]);
    }

    public function exportPdf(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('export', $meeting);

        if ($meeting->status !== MeetingStatus::Ready) {
            return $this->error('Meeting is not ready for export.', 409);
        }

        GeneratePdfExportJob::dispatch($meeting);

        return $this->success([
            'message' => 'PDF export queued. You will be notified when it is ready.',
        ], 202);
    }

}
