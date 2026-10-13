<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies an existing user that they have been added to a workspace.
 *
 * Unlike TeamInvitationMail this is not a token-based invitation: the user
 * already has an account and is added to the tenant immediately, so the mail
 * links to the dashboard rather than an acceptance URL.
 */
class TenantMemberAddedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $tenantName,
        public string $dashboardUrl,
        public ?string $inviterName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been added to '.$this->tenantName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-member-added',
            with: [
                'userName' => $this->userName,
                'tenantName' => $this->tenantName,
                'dashboardUrl' => $this->dashboardUrl,
                'inviterName' => $this->inviterName,
            ],
        );
    }
}
