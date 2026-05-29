<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\GeneratePdfExportJob;
use App\Jobs\SendNotificationEmailJob;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratePdfExportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_and_notifies_user(): void
    {
        config(['security.pdf_disk' => 's3']);
        Storage::fake('s3');
        Queue::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        $user = User::factory()->create([
            'notification_preferences' => [
                'pdf_ready' => ['in_app' => true, 'email' => true],
            ],
        ]);
        $meeting = Meeting::factory()->for($user)->ready()->create();
        TranscriptSegment::query()->create([
            'meeting_id' => $meeting->id,
            'speaker_label' => 'Rep',
            'body' => 'Hello prospect',
            'start_ms' => 0,
            'end_ms' => 1000,
        ]);

        (new GeneratePdfExportJob($meeting))->handle(
            app(\App\Services\StorageService::class),
            app(\App\Services\AuditService::class),
            app(\App\Services\NotificationService::class),
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'pdf_ready',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $user->id,
            'event_type' => 'pdf.exported',
            'entity_type' => 'meeting',
            'entity_id' => $meeting->id,
        ]);
        Queue::assertPushed(SendNotificationEmailJob::class);

        $files = Storage::disk('s3')->allFiles("exports/{$meeting->user_id}/{$meeting->id}");
        $this->assertNotEmpty($files);
    }

    public function test_fails_when_meeting_not_ready(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->create([
            'status' => MeetingStatus::Processing->value,
        ]);

        $job = new GeneratePdfExportJob($meeting);
        $job->handle(
            app(\App\Services\StorageService::class),
            app(\App\Services\AuditService::class),
            app(\App\Services\NotificationService::class),
        );

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => 'pdf_ready',
        ]);
    }
}
