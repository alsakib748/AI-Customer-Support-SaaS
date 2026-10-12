<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email sent to a user who has been added to a workspace.
 */
class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $acceptUrl,
        public string $workspaceName,
        public string $role,
        public ?string $inviterName = null,
        public ?string $expiresAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join '.$this->workspaceName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'acceptUrl' => $this->acceptUrl,
                'workspaceName' => $this->workspaceName,
                'role' => $this->role,
                'inviterName' => $this->inviterName,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
