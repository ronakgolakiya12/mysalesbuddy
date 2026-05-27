<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePromptRequest;
use App\Http\Resources\CoachingPromptVersionResource;
use App\Models\CoachingPromptVersion;
use App\Services\AuditService;
use App\Services\CoachingPromptService;
use App\Support\Enums\AuditEventType;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly CoachingPromptService $prompts,
        private readonly AuditService $audit
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $versions = $this->prompts->getVersionHistory($user);

        return $this->success(CoachingPromptVersionResource::collection($versions));
    }

    public function store(UpdatePromptRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $version = $this->prompts->createVersion($user, (string) $request->validated('prompt_text'));

        $this->audit->log(
            user: $user,
            event: AuditEventType::PromptVersionCreated,
            entityType: 'coaching_prompt_version',
            entityId: (string) $version->id,
            metadata: ['action' => 'create']
        );

        return response()->json(
            ['data' => (new CoachingPromptVersionResource($version))->toArray($request)],
            201
        );
    }

    public function restore(Request $request, CoachingPromptVersion $version): JsonResponse
    {
        $user = $request->user();
        if ($user === null || (string) $version->user_id !== (string) $user->id) {
            return $this->error('This action is unauthorized.', 403);
        }

        $newVersion = $this->prompts->restoreVersion($user, $version);

        $this->audit->log(
            user: $user,
            event: AuditEventType::PromptVersionCreated,
            entityType: 'coaching_prompt_version',
            entityId: (string) $newVersion->id,
            metadata: [
                'action' => 'restore',
                'restored_from' => (string) $version->id,
            ]
        );

        return response()->json(
            ['data' => (new CoachingPromptVersionResource($newVersion))->toArray($request)],
            201
        );
    }
}
