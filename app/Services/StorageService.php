<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StorageService
{
    /**
     * Disks where pre-signed URLs work natively (S3 and S3-compatible).
     * For everything else (local, public, sftp, etc.) we fall back to a
     * Laravel-signed download route.
     */
    private const NATIVELY_SIGNABLE_DRIVERS = ['s3'];

    private function avatarDisk(): string
    {
        return (string) (config('security.avatar_disk') ?? config('filesystems.default', 'local'));
    }

    private function pdfDisk(): string
    {
        return (string) (config('security.pdf_disk') ?? config('filesystems.default', 'local'));
    }

    /**
     * @throws ValidationException
     */
    public function storeAvatar(UploadedFile $file, User $user): string
    {
        Validator::make(
            ['avatar' => $file],
            ['avatar' => ['required', 'file', 'mimes:jpeg,png,gif,webp', 'max:2048']],
        )->validate();

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $path = "avatars/{$user->id}/".(string) Str::uuid().'.'.$extension;

        Storage::disk($this->avatarDisk())->put($path, $file->get(), 'private');

        return $path;
    }

    public function deleteAvatar(string $path): void
    {
        if ($path === '') {
            return;
        }

        try {
            Storage::disk($this->avatarDisk())->delete($path);
        } catch (Throwable $e) {
            Log::warning('Failed to delete avatar from storage.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getSignedAvatarUrl(string $path, int $minutes = 60): ?string
    {
        if ($path === '') {
            return null;
        }

        return $this->signedUrlFor($this->avatarDisk(), $path, $minutes, 'avatars.download');
    }

    public function storePdf(Meeting $meeting, string $contents): string
    {
        $path = "exports/{$meeting->user_id}/{$meeting->id}/".(string) Str::uuid().'.pdf';
        Storage::disk($this->pdfDisk())->put($path, $contents, 'private');

        return $path;
    }

    public function getPdfSignedUrl(string $path, int $minutes = 60 * 24 * 7): ?string
    {
        if ($path === '') {
            return null;
        }

        return $this->signedUrlFor($this->pdfDisk(), $path, $minutes, 'storage.download');
    }

    /**
     * Get the underlying disk driver name (e.g. 's3', 'local', 'public').
     */
    public function diskFor(string $disk): string
    {
        return (string) config("filesystems.disks.{$disk}.driver", $disk);
    }

    /**
     * Read raw file contents from the configured PDF disk. Used by the
     * server-side download route to stream the file regardless of driver.
     */
    public function readPdf(string $path): ?string
    {
        $filesystem = Storage::disk($this->pdfDisk());
        if (! $filesystem->exists($path)) {
            return null;
        }

        return $filesystem->get($path);
    }

    public function pdfMimeType(string $path): string
    {
        return Storage::disk($this->pdfDisk())->mimeType($path) ?: 'application/pdf';
    }

    private function signedUrlFor(string $disk, string $path, int $minutes, string $fallbackRoute): ?string
    {
        $driver = $this->diskFor($disk);

        // S3-style disks generate their own pre-signed URLs that don't go through Laravel.
        if (in_array($driver, self::NATIVELY_SIGNABLE_DRIVERS, true)) {
            try {
                $filesystem = Storage::disk($disk);
                if (method_exists($filesystem, 'temporaryUrl')) {
                    return $filesystem->temporaryUrl($path, now()->addMinutes($minutes));
                }
            } catch (Throwable $e) {
                Log::warning('Failed to generate signed URL.', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        // local / public / anything else — build a Laravel-signed download URL.
        // The route streams the file content from whatever disk it lives on.
        try {
            return URL::temporarySignedRoute(
                $fallbackRoute,
                now()->addMinutes($minutes),
                ['path' => $path],
            );
        } catch (Throwable $e) {
            Log::warning('Failed to build signed download route.', [
                'route' => $fallbackRoute,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
