<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Meeting;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the StorageService to write to 's3' so Storage::fake('s3')
        // intercepts the writes the tests assert against.
        config([
            'security.avatar_disk' => 's3',
            'security.pdf_disk' => 's3',
        ]);
    }

    public function test_store_avatar_uploads_file_to_disk_and_returns_path(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('me.png', 200, 200);

        $service = new StorageService();
        $path = $service->storeAvatar($file, $user);

        $this->assertStringStartsWith("avatars/{$user->id}/", $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_store_avatar_rejects_invalid_mime(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream');

        $service = new StorageService();

        $this->expectException(ValidationException::class);
        $service->storeAvatar($file, $user);
    }

    public function test_store_avatar_rejects_oversize_file(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        // 3 MB > 2048 KB limit
        $file = UploadedFile::fake()->image('huge.png')->size(3072);

        $service = new StorageService();

        $this->expectException(ValidationException::class);
        $service->storeAvatar($file, $user);
    }

    public function test_delete_avatar_removes_file(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('avatars/u1/file.png', 'binary');

        $service = new StorageService();
        $service->deleteAvatar('avatars/u1/file.png');

        Storage::disk('s3')->assertMissing('avatars/u1/file.png');
    }

    public function test_delete_avatar_is_noop_for_empty_path(): void
    {
        Storage::fake('s3');

        $service = new StorageService();
        $service->deleteAvatar('');

        $this->assertTrue(true); // no exception
    }

    public function test_store_pdf_writes_under_meeting_namespace_and_returns_path(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $service = new StorageService();
        $path = $service->storePdf($meeting, '%PDF-binary');

        $this->assertStringStartsWith("exports/{$user->id}/{$meeting->id}/", $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('s3')->assertExists($path);
    }
}
