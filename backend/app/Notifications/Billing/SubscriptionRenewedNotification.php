<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Subscription $subscription)
    {
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
        $plan = $this->subscription->plan;

        return (new MailMessage)
            ->subject('Subscription Renewed')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been renewed successfully.')
            ->line('**Plan:** ' . $plan->name)
            ->line('**New Period:** ' . $this->subscription->starts_at->format('M d, Y')
                . ' - ' . $this->subscription->ends_at->format('M d, Y'))
            ->line('**Next Renewal:** ' . $this->subscription->next_billing_at->format('M d, Y'))
            ->action('View Subscription', url('/billing'))
            ->line('Thank you for your continued business!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_renewed',
            'subscription_id' => $this->subscription->id,
            'ends_at' => $this->subscription->ends_at?->toISOString(),
            'message' => 'Your subscription has been renewed.',
        ];
    }
}