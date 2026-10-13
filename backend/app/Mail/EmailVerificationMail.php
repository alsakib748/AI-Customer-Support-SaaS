<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email verification link. The verification hash is delivered only here — never
 * in an API response.
 */
class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $verifyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your email address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: [
                'verifyUrl' => $this->verifyUrl,
                'userName' => $this->user->full_name ?: $this->user->first_name,
            ],
        );
    }
}
