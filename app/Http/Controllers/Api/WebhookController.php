<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\ProcessBotInCallJob;
use App\Jobs\Webhooks\ProcessBotJoiningJob;
use App\Jobs\Webhooks\ProcessBotLeftCallJob;
use App\Jobs\Webhooks\ProcessTranscriptFailedJob;
use App\Jobs\Webhooks\ProcessTranscriptReadyJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function recallWebhook(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');
        $botId = (string) $request->input('data.bot_id', '');
        $data = $request->input('data', []);
        $context = is_array($data) ? $data : [];
        $statusCode = $request->input('data.status_code');
        $statusCode = is_string($statusCode) ? $statusCode : null;

        if ($botId === '') {
            Log::warning('recall.webhook.missing_bot_id', ['event' => $event]);

            return response()->json(['received' => true]);
        }

        match ($event) {
            'bot.joining_call' => ProcessBotJoiningJob::dispatch($botId),
            'bot.in_call_recording', 'bot.in_call' => ProcessBotInCallJob::dispatch($botId),
            'bot.call_ended', 'bot.left_call', 'bot.done' => ProcessBotLeftCallJob::dispatch($botId, $statusCode),
            'transcript.done', 'bot.transcript_ready' => ProcessTranscriptReadyJob::dispatch($botId),
            'transcript.failed', 'bot.transcript_failed', 'bot.fatal' => ProcessTranscriptFailedJob::dispatch($botId, $context),
            default => Log::info('recall.webhook.unhandled_event', [
                'event' => $event,
                'bot_id' => $botId,
            ]),
        };

        return response()->json(['received' => true]);
    }
}
