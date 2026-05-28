<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SyncCalendarMeetingsAction;
use App\Exceptions\CalendarNotConnectedException;
use App\Exceptions\CalendarTokenExpiredException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalendarSyncController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SyncCalendarMeetingsAction $action) {}

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        try {
            $result = $this->action->execute($user);
        } catch (CalendarNotConnectedException | CalendarTokenExpiredException $e) {
            // Re-throw so the bootstrap exception renderer returns the 422 JSON shape
            // with the appropriate error_code field.
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to sync calendar meetings.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to sync calendar events.', 502);
        }

        return $this->success($result);
    }
}
