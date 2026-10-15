<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Suspended - Action Required')
            ->greeting('Hello '.($notifiable->first_name ?? 'there').'!')
            ->line('We have not received payment for your subscription, and access to your workspace has been suspended.')
            ->line('To restore access, please complete your payment.')
            ->action('Pay Now', url('/billing/pay'))
            ->line('If you have already made a payment, it may take a few minutes to process.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_suspended',
            'subscription_id' => $this->subscription->id,
            'message' => 'Subscription suspended due to non-payment. Pay now to restore access.',
        ];
    }
}
