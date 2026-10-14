<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Demo Request - '.config('app.name'),
            to: [config('mail.from.address', config('mail.support_email', 'support@example.local'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.demo-request-notification',
            with: [
                'demoRequest' => $this->demoRequest,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
