<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public User $user,
        public string $mailClass,
        public array $payload
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (! class_exists($this->mailClass)) {
            Log::warning('notification.email.mail_class_missing', [
                'mail_class' => $this->mailClass,
                'user_id' => $this->user->id,
            ]);

            return;
        }

        /** @var Mailable $mailable */
        $mailable = new $this->mailClass($this->user, $this->payload);

        Mail::to($this->user->email)->send($mailable);
    }
}
