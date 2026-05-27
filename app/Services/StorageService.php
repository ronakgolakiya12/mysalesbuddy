<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StorageService
{
    private function avatarDisk(): string
    {
        return (string) config('security.avatar_disk', 's3');
    }

    private function pdfDisk(): string
    {
        return (string) config('security.pdf_disk', 's3');
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

        return $this->signedUrlFor($this->avatarDisk(), $path, $minutes);
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

        return $this->signedUrlFor($this->pdfDisk(), $path, $minutes);
    }

    private function signedUrlFor(string $disk, string $path, int $minutes): ?string
    {
        try {
            $filesystem = Storage::disk($disk);

            if (method_exists($filesystem, 'temporaryUrl')) {
                return $filesystem->temporaryUrl($path, now()->addMinutes($minutes));
            }

            if (method_exists($filesystem, 'url')) {
                return $filesystem->url($path);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to generate signed URL.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
