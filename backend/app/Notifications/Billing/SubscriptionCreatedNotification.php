<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification
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
            ->subject('Welcome to ' . $plan->name . '!')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your subscription has been created successfully.')
            ->line('**Plan:** ' . $plan->name)
            ->line('**Billing Cycle:** ' . ucfirst($this->subscription->billing_cycle))
            ->line('**Status:** ' . $this->subscription->status_label)
            ->when($this->subscription->is_trialing, function ($mail) {
                return $mail->line('**Trial Ends:** ' . $this->subscription->trial_ends_at->format('M d, Y'));
            })
            ->action('View Subscription', url('/billing'))
            ->line('Thank you for choosing our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_created',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'status' => $this->subscription->status,
            'message' => 'Your subscription has been created.',
        ];
    }
}