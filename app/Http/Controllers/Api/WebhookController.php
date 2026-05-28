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
    /**
     * Recall.ai webhook payload shape (Standard Webhooks delivery):
     * {
     *   "event": "bot.joining_call",
     *   "data": {
     *     "bot":  { "id": "<uuid>", "metadata": {} },
     *     "data": { "code": "joining_call", "sub_code": null, "updated_at": "..." }
     *   }
     * }
     *
     * Legacy fallback (older webhook deliveries flatten the bot id):
     *   "data": { "bot_id": "<uuid>", "status_code": "..." }
     */
    public function recallWebhook(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');
        $botId = (string) $request->input('data.bot.id');
        // sub_code carries the "why" for terminal events (e.g. 'meeting_not_started'
        // on bot.call_ended). data.data.code echoes the event name and isn't useful
        // as a block reason. Null for events that don't carry a sub-code.
        $subCode = $request->input('data.data.sub_code');
        $statusCode = is_string($subCode) && $subCode !== '' ? $subCode : null;
        $innerData = $request->input('data.data', $request->input('data', []));
        $context = is_array($innerData) ? $innerData : [];

        if ($botId === '') {
            Log::warning('recall.webhook.missing_bot_id', [
                'event' => $event,
                'payload_keys' => array_keys((array) $request->input('data', [])),
            ]);

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
