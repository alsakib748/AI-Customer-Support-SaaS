<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Subscription $subscription,
        public int $daysBefore
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
        $plan = $this->subscription->plan;
        $amount = $plan->getPriceForCycle($this->subscription->billing_cycle);

        return (new MailMessage)
            ->subject('Subscription Renewal Reminder')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription will renew in ' . $this->daysBefore . ' day(s).')
            ->line('**Plan:** ' . $plan->name)
            ->line('**Amount:** ' . $plan->currency . ' ' . number_format($amount, 2))
            ->line('**Renewal Date:** ' . $this->subscription->next_billing_at->format('M d, Y'))
            ->action('Manage Subscription', url('/billing'))
            ->line('If you want to cancel, please do so before the renewal date.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'renewal_reminder',
            'subscription_id' => $this->subscription->id,
            'days_before' => $this->daysBefore,
            'renewal_date' => $this->subscription->next_billing_at->toISOString(),
            'message' => "Your subscription will renew in {$this->daysBefore} days.",
        ];
    }
}