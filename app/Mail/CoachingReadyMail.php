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

class CoachingReadyMail extends Mailable implements ShouldQueue
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
        return new Envelope(subject: 'Your coaching analysis is ready');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.coaching-ready',
            with: [
                'user' => $this->user,
                'payload' => $this->payload,
            ],
        );
    }
}
