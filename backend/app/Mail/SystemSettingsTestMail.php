<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Plain test email used by the Email settings "Send test email" action.
 */
class SystemSettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $payload = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'System Settings — test email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system-settings-test',
            with: [
                'platformName' => $this->payload['platform_name'] ?? config('app.name'),
                'sentAt'       => now()->toDateTimeString(),
            ],
        );
    }
}
