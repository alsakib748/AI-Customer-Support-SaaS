<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
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
        $price = $plan?->getPriceForCycle($this->subscription->billing_cycle) ?? 0;

        return (new MailMessage)
            ->subject("Trial ending in {$this->daysRemaining} day(s)")
            ->greeting('Hello ' . ($notifiable->first_name ?? 'there') . '!')
            ->line("Your free trial of {$plan?->name} will end in {$this->daysRemaining} day(s).")
            ->line('**Amount:** '
                . ($plan?->currency ?? 'USD') . ' '
                . number_format($price, 2)
                . ' / ' . $this->subscription->billing_cycle)
            ->line('**Trial Ends:** '
                . optional($this->subscription->trial_ends_at)->format('M d, Y'))
            ->action('Manage Subscription', url('/billing'))
            ->line('Add a payment method to continue using our service.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'subscription_id' => $this->subscription->id,
            'days_remaining' => $this->daysRemaining,
            'trial_ends_at' => $this->subscription->trial_ends_at->toISOString(),
            'message' => "Trial ends in {$this->daysRemaining} days",
        ];
    }
}
