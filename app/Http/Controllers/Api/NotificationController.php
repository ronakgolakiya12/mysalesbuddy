<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use App\Services\NotificationService;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $unread = $this->notifications->getUnread($user);

        return $this->success(AppNotificationResource::collection($unread));
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        unset($request);

        $this->notifications->markRead($notification);

        return $this->noContent();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $count = $this->notifications->markAllRead($user);

        return $this->success(['updated' => $count]);
    }
}
