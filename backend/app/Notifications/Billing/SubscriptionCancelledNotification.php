<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelledNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Subscription $subscription,
        public bool $immediately
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Subscription Cancelled')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been cancelled.');

        if (!$this->immediately) {
            $message->line('**Access Until:** ' . $this->subscription->ends_at->format('M d, Y'));
        }

        return $message
            ->action('Resume Subscription', url('/billing'))
            ->line('We\'re sorry to see you go. You can resume anytime before your access ends.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_cancelled',
            'subscription_id' => $this->subscription->id,
            'immediately' => $this->immediately,
            'ends_at' => $this->subscription->ends_at?->toISOString(),
            'message' => 'Your subscription has been cancelled.',
        ];
    }
}