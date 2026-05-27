<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\PdfReadyMail;
use App\Models\Meeting;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\StorageService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\MeetingStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;

class GeneratePdfExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public Meeting $meeting)
    {
        $this->onQueue('default');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(
        StorageService $storage,
        AuditService $audit,
        NotificationService $notifications
    ): void {
        $meeting = $this->meeting->fresh();
        if ($meeting === null) {
            return;
        }

        if ($meeting->status !== MeetingStatus::Ready) {
            $this->fail(new RuntimeException('Meeting is not in Ready status; cannot export PDF.'));

            return;
        }

        $meeting->load(['user', 'transcriptSegments', 'latestCoachingAnalysis']);

        $segments = $meeting->transcriptSegments()->orderBy('start_ms')->get();
        $analysis = $meeting->latestCoachingAnalysis;

        $html = View::make('exports.meeting-pdf', [
            'meeting' => $meeting,
            'segments' => $segments,
            'analysis' => $analysis,
        ])->render();

        $pdfBytes = Pdf::loadHTML($html)->setPaper('A4', 'portrait')->output();

        $path = $storage->storePdf($meeting, $pdfBytes);
        $downloadUrl = $storage->getPdfSignedUrl($path);

        $audit->log(
            user: $meeting->user,
            event: AuditEventType::PdfExported,
            entityType: 'meeting',
            entityId: (string) $meeting->id,
            metadata: [
                'path' => $path,
                'has_coaching' => $analysis !== null,
                'segment_count' => $segments->count(),
            ]
        );

        Log::info('meeting.pdf_exported', [
            'meeting_id' => $meeting->id,
            'path' => $path,
        ]);

        $notifications->notifyAndMail(
            $meeting->user,
            'pdf_ready',
            [
                'meeting_id' => $meeting->id,
                'meeting_title' => $meeting->title,
                'download_url' => $downloadUrl,
                'path' => $path,
            ],
            PdfReadyMail::class
        );
    }
}
