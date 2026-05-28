<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams files (PDFs, avatars) from whatever filesystem disk is configured.
 *
 * Routes registered without auth:sanctum but protected by Laravel's signed
 * middleware — the URL contains an HMAC signature + expiry that only the
 * server can produce, so the link itself is the access token.
 */
class StorageDownloadController extends Controller
{
    public function __construct(private readonly StorageService $storage)
    {
    }

    public function pdf(Request $request): StreamedResponse
    {
        return $this->streamFile($request, attachmentPrefix: 'meeting-export');
    }

    public function avatar(Request $request): StreamedResponse
    {
        return $this->streamFile($request, attachmentPrefix: null);
    }

    private function streamFile(Request $request, ?string $attachmentPrefix): StreamedResponse
    {
        $path = (string) $request->query('path', '');
        if ($path === '') {
            abort(404);
        }

        $contents = $this->storage->readPdf($path);
        if ($contents === null) {
            abort(404);
        }

        $filename = $attachmentPrefix !== null
            ? $attachmentPrefix.'-'.basename($path)
            : basename($path);

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            [
                'Content-Type' => $this->storage->pdfMimeType($path),
                'Content-Length' => (string) strlen($contents),
            ],
            $attachmentPrefix !== null ? 'attachment' : 'inline',
        );
    }
}
