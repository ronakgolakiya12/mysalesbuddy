<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\NewNotification;
use App\Jobs\SendNotificationEmailJob;
use App\Mail\PdfReadyMail;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_creates_notification_and_broadcasts(): void
    {
        Event::fake([NewNotification::class]);

        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $notification = $service->notify($user, 'pdf_ready', ['meeting_id' => 'abc']);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'pdf_ready',
        ]);
        $this->assertNull($notification->read_at);

        Event::assertDispatched(NewNotification::class, function (NewNotification $e) use ($notification): bool {
            return $e->notification->id === $notification->id;
        });
    }

    public function test_notify_and_mail_dispatches_email_job_when_user_prefers_email(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'notification_preferences' => [
                'pdf_ready' => ['in_app' => true, 'email' => true],
            ],
        ]);

        app(NotificationService::class)->notifyAndMail(
            $user,
            'pdf_ready',
            ['meeting_id' => 'abc'],
            PdfReadyMail::class
        );

        Queue::assertPushed(SendNotificationEmailJob::class);
    }

    public function test_notify_and_mail_skips_email_when_user_disables_it(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'notification_preferences' => [
                'pdf_ready' => ['in_app' => true, 'email' => false],
            ],
        ]);

        app(NotificationService::class)->notifyAndMail(
            $user,
            'pdf_ready',
            ['meeting_id' => 'abc'],
            PdfReadyMail::class
        );

        Queue::assertNotPushed(SendNotificationEmailJob::class);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'pdf_ready',
        ]);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);

        app(NotificationService::class)->markRead($notification);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_updates_only_unread(): void
    {
        $user = User::factory()->create();
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'transcript_failed',
            'payload_json' => [],
            'read_at' => null,
        ]);

        $count = app(NotificationService::class)->markAllRead($user);

        $this->assertSame(2, $count);
        $this->assertSame(0, $user->notifications()->whereNull('read_at')->count());
    }

    public function test_get_unread_returns_only_unread_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'transcript_failed',
            'payload_json' => [],
            'read_at' => now(),
        ]);
        AppNotification::query()->create([
            'user_id' => $other->id,
            'type' => 'pdf_ready',
            'payload_json' => [],
            'read_at' => null,
        ]);

        $unread = app(NotificationService::class)->getUnread($user);

        $this->assertCount(1, $unread);
        $this->assertSame('pdf_ready', $unread->first()->type);
    }
}
