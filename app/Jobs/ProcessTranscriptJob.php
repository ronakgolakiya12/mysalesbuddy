<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MeetingStatusUpdated;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Services\AuditService;
use App\Services\RecallAiService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\CoachingMode;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessTranscriptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const SEGMENT_GAP_MS = 2000;

    public int $tries = 3;

    public function __construct(public Meeting $meeting)
    {
        $this->onQueue('default');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(RecallAiService $recall, AuditService $audit): void
    {
        $meeting = $this->meeting->fresh();
        if ($meeting === null) {
            return;
        }

        if ($meeting->status === MeetingStatus::Ready) {
            return;
        }

        if ($meeting->recall_bot_id === null) {
            return;
        }

        $raw = $recall->getTranscript($meeting->recall_bot_id);
        $segments = $this->parseSegments($raw);

        $analysis = DB::transaction(function () use ($meeting, $segments, $audit): CoachingAnalysis {
            $meeting->transcriptSegments()->delete();

            if ($segments !== []) {
                $now = Carbon::now();
                $rows = array_map(static fn (array $segment): array => [
                    'id' => (string) Str::uuid(),
                    'meeting_id' => $meeting->id,
                    'speaker_label' => $segment['speaker'],
                    'body' => $segment['text'],
                    'start_ms' => $segment['start_ms'],
                    'end_ms' => $segment['end_ms'],
                    'created_at' => $now,
                ], $segments);

                TranscriptSegment::insert($rows);
            }

            [$repPct, $prospectPct] = $this->calculateTalkTime($segments);

            $meeting->update([
                'status' => MeetingStatus::Ready,
            ]);

            $analysis = CoachingAnalysis::create([
                'meeting_id' => $meeting->id,
                'prompt_version_id' => null,
                'mode' => CoachingMode::TranscriptOnly,
                'talk_time_rep' => $repPct,
                'talk_time_prospect' => $prospectPct,
                'triggered_by' => 'auto',
            ]);

            $audit->log(
                user: $meeting->user,
                event: AuditEventType::TranscriptProcessed,
                entityType: 'meeting',
                entityId: (string) $meeting->id,
                metadata: ['segment_count' => count($segments)]
            );

            $audit->log(
                user: $meeting->user,
                event: AuditEventType::CoachingTriggered,
                entityType: 'coaching_analysis',
                entityId: (string) $analysis->id,
                metadata: [
                    'triggered_by' => 'auto',
                    'mode' => CoachingMode::TranscriptOnly->value,
                ]
            );

            return $analysis;
        });

        broadcast(new MeetingStatusUpdated($meeting->fresh()));

        \App\Jobs\CoachingAnalysisJob::dispatch(
            $meeting,
            $analysis->id,
            CoachingMode::TranscriptOnly->value
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array{speaker: string, text: string, start_ms: int, end_ms: int}>
     */
    private function parseSegments(array $raw): array
    {
        $segments = [];
        $current = null;

        foreach ($raw as $entry) {
            $speaker = (string) ($entry['speaker'] ?? 'Unknown');
            $words = is_array($entry['words'] ?? null) ? $entry['words'] : [];

            foreach ($words as $word) {
                $text = (string) ($word['text'] ?? '');
                if ($text === '') {
                    continue;
                }

                $startMs = (int) round(((float) ($word['start_time'] ?? 0)) * 1000);
                $endMs = (int) round(((float) ($word['end_time'] ?? 0)) * 1000);

                $shouldStartNew = $current === null
                    || $current['speaker'] !== $speaker
                    || ($startMs - $current['end_ms']) > self::SEGMENT_GAP_MS;

                if ($shouldStartNew) {
                    if ($current !== null) {
                        $segments[] = $current;
                    }
                    $current = [
                        'speaker' => $speaker,
                        'text' => $text,
                        'start_ms' => $startMs,
                        'end_ms' => $endMs,
                    ];
                    continue;
                }

                $current['text'] .= ' '.$text;
                $current['end_ms'] = $endMs;
            }
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * @param  array<int, array{speaker: string, text: string, start_ms: int, end_ms: int}>  $segments
     * @return array{0: int, 1: int}
     */
    private function calculateTalkTime(array $segments): array
    {
        if ($segments === []) {
            return [0, 0];
        }

        $totalMs = 0;
        foreach ($segments as $segment) {
            $totalMs += max(0, $segment['end_ms'] - $segment['start_ms']);
        }
        if ($totalMs === 0) {
            return [0, 0];
        }

        $firstSpeaker = $segments[0]['speaker'];
        $repMs = 0;
        foreach ($segments as $segment) {
            if ($segment['speaker'] === $firstSpeaker) {
                $repMs += max(0, $segment['end_ms'] - $segment['start_ms']);
            }
        }

        $repPct = (int) round(($repMs / $totalMs) * 100);
        $repPct = max(0, min(100, $repPct));

        return [$repPct, 100 - $repPct];
    }
}
