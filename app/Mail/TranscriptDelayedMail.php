<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TranscriptDelayedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public User $user, public array $payload)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your transcript is taking longer than usual');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.transcript-delayed',
            with: [
                'user' => $this->user,
                'payload' => $this->payload,
            ],
        );
    }
}
