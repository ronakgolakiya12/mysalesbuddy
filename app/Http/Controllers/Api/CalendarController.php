<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OauthConnection;
use App\Services\CalendarService;
use App\Support\Enums\OAuthProvider;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalendarController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly CalendarService $calendar)
    {
    }

    public function upcomingEvents(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $connection = OauthConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::Google->value)
            ->first();

        if ($connection === null) {
            return $this->error('Google Calendar is not connected.', 404);
        }

        if ($connection->isExpired()) {
            return $this->error('Google Calendar connection has expired. Please reconnect.', 409);
        }

        $days = (int) $request->query('days', '7');
        $days = max(1, min(30, $days));

        try {
            $events = $this->calendar->getUpcomingGoogleEvents($connection, $days);
        } catch (Throwable $e) {
            Log::error('Failed to fetch upcoming Google Calendar events.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to fetch calendar events.', 502);
        }

        return $this->success($events);
    }
}
