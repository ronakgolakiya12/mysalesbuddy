<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MeetingStatusUpdated;
use App\Exceptions\DuplicateBotException;
use App\Models\Meeting;
use App\Models\NotetakerConfig;
use App\Services\AuditService;
use App\Services\RecallAiService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Facades\DB;

class DispatchBotAction
{
    public function __construct(
        private readonly RecallAiService $recall,
        private readonly AuditService $audit
    ) {}

    public function execute(Meeting $meeting): Meeting
    {
        $result = DB::transaction(function () use ($meeting) {
            $meeting->refresh();

            // Idempotency: if a bot_id is already set and we're past scheduled, skip.
            if ($meeting->recall_bot_id !== null && $meeting->status !== MeetingStatus::Scheduled) {
                return ['meeting' => $meeting, 'dispatched' => false];
            }

            // One-bot-per-URL rule: another non-terminal meeting with the same URL.
            $nonTerminalStatuses = [
                MeetingStatus::Scheduled->value,
                MeetingStatus::BotJoining->value,
                MeetingStatus::Recording->value,
                MeetingStatus::Processing->value,
            ];

            $conflict = Meeting::query()
                ->where('external_meeting_url', $meeting->external_meeting_url)
                ->where('id', '!=', $meeting->id)
                ->whereNotNull('recall_bot_id')
                ->whereIn('status', $nonTerminalStatuses)
                ->lockForUpdate()
                ->first();

            if ($conflict !== null) {
                throw new DuplicateBotException((string) $conflict->id);
            }

            $config = NotetakerConfig::query()
                ->where('user_id', $meeting->user_id)
                ->first();

            $displayName = $config?->display_name ?? 'Sales Buddy';

            $payload = [
                'meeting_url' => $meeting->external_meeting_url,
                'bot_name' => $displayName,
                'recording_config' => [
                    'transcript' => [
                        'provider' => [
                            'meeting_captions' => (object) [],
                        ],
                    ],
                ],
            ];

            if ($config?->intro_message) {
                $payload['chat'] = [
                    'on_bot_join' => [
                        'send_to' => 'everyone',
                        'message' => $config->intro_message,
                    ],
                ];
            }

            $response = $this->recall->createBot($payload);
            $botId = isset($response['id']) ? (string) $response['id'] : null;

            $meeting->fill([
                'recall_bot_id' => $botId,
                'status' => MeetingStatus::BotJoining,
            ]);
            $meeting->save();

            $this->audit->log(
                user: $meeting->user,
                event: AuditEventType::MeetingBotDispatched,
                entityType: 'meeting',
                entityId: (string) $meeting->id,
                metadata: ['bot_id' => $botId]
            );

            DB::afterCommit(function () use ($meeting): void {
                broadcast(new MeetingStatusUpdated($meeting));
            });

            return ['meeting' => $meeting, 'dispatched' => true];
        });

        return $result['meeting'];
    }
}
