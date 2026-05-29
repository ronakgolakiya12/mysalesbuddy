<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MeetingStatusUpdated;
use App\Exceptions\DuplicateBotException;
use App\Models\Meeting;
use App\Models\NotetakerConfig;
use App\Services\AuditService;
use App\Services\RecallAiService;
use App\Services\StorageService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchBotAction
{
    /** Recall.ai recommends 1280x720 for bot video output. */
    private const AVATAR_TARGET_WIDTH = 1280;

    private const AVATAR_TARGET_HEIGHT = 720;

    public function __construct(
        private readonly RecallAiService $recall,
        private readonly AuditService $audit,
        private readonly StorageService $storage
    ) {
    }

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

            $displayName = $config !== null ? $config->display_name : 'Sales Buddy';

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

            // Forward the notetaker avatar (if set) so Recall.ai displays it
            // as the bot's video output in the meeting. Best-effort: failures
            // never block bot creation.
            $avatarPayload = $this->buildAvatarPayload($config);
            if ($avatarPayload !== null) {
                $payload['automatic_video_output'] = $avatarPayload;
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

    /**
     * Build the `automatic_video_output` block for Recall.ai from the user's
     * configured avatar. Returns null if no avatar is set or the image can't
     * be processed — never throws upstream.
     *
     * @return array<string, mixed>|null
     */
    private function buildAvatarPayload(?NotetakerConfig $config): ?array
    {
        if ($config === null || ! $config->avatar_path) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            Log::warning('GD extension unavailable; skipping bot avatar.');

            return null;
        }

        $bytes = $this->storage->readAvatar((string) $config->avatar_path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        try {
            $source = @imagecreatefromstring($bytes);
            if ($source === false) {
                Log::warning('Failed to decode avatar image for Recall.ai', [
                    'path' => $config->avatar_path,
                ]);

                return null;
            }

            $jpeg = $this->resizeAvatarToJpeg($source);
            imagedestroy($source);

            if ($jpeg === null) {
                return null;
            }

            return [
                'in_call_recording' => [
                    'kind' => 'jpeg',
                    'b64_data' => base64_encode($jpeg),
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to prepare avatar for Recall.ai', [
                'path' => $config->avatar_path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Re-encode any GD image resource to a 1280x720 JPEG. The avatar is
     * centered on a black canvas (letterboxed) so it isn't distorted by the
     * aspect-ratio change.
     *
     * @param  \GdImage  $source
     */
    private function resizeAvatarToJpeg($source): ?string
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        $scale = min(self::AVATAR_TARGET_WIDTH / $srcW, self::AVATAR_TARGET_HEIGHT / $srcH);
        $scaledW = max(1, (int) round($srcW * $scale));
        $scaledH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor(self::AVATAR_TARGET_WIDTH, self::AVATAR_TARGET_HEIGHT);
        if ($canvas === false) {
            return null;
        }

        $black = imagecolorallocate($canvas, 0, 0, 0);
        if ($black !== false) {
            imagefill($canvas, 0, 0, $black);
        }

        $offsetX = (int) ((self::AVATAR_TARGET_WIDTH - $scaledW) / 2);
        $offsetY = (int) ((self::AVATAR_TARGET_HEIGHT - $scaledH) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $offsetX,
            $offsetY,
            0,
            0,
            $scaledW,
            $scaledH,
            $srcW,
            $srcH,
        );

        ob_start();
        $ok = imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return $ok && $jpeg !== '' ? $jpeg : null;
    }
}
