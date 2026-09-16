<?php

namespace App\Notifications\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Subscription $subscription,
        public ?Plan $oldPlan,
        public Plan $newPlan
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
        return (new MailMessage)
            ->subject('Subscription Upgraded! 🚀')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been upgraded successfully.')
            ->line('**Previous Plan:** ' . ($this->oldPlan?->name ?? 'None'))
            ->line('**New Plan:** ' . $this->newPlan->name)
            ->line('**New Limits:**')
            ->line('• AI Messages: ' . $this->subscription->ai_limit)
            ->line('• Team Members: ' . $this->subscription->agents_limit)
            ->line('• Documents: ' . $this->subscription->documents_limit)
            ->action('View Subscription', url('/billing'))
            ->line('Enjoy your new features!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_upgraded',
            'subscription_id' => $this->subscription->id,
            'old_plan' => $this->oldPlan?->name,
            'new_plan' => $this->newPlan->name,
            'message' => 'Your subscription has been upgraded to ' . $this->newPlan->name,
        ];
    }
}