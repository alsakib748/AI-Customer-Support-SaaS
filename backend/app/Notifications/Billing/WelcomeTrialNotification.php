<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeTrialNotification extends Notification
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
        $trialEnds = $this->subscription->trial_ends_at;

        $message = (new MailMessage)
            ->subject('Welcome! Your free trial has started')
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . '!')
            ->line('Welcome to ' . config('app.name') . '!')
            ->line('You\'re on the **' . ($plan?->name ?? 'Free') . '** plan.');

        if ($trialEnds) {
            $days = max(0, now()->diffInDays($trialEnds, false));
            $message
                ->line('Your trial ends on **' . $trialEnds->format('M d, Y') . '** (' . $days . ' days).')
                ->line('**What you get:**')
                ->line('• ' . ($plan?->getLimit('agents.max', 2)) . ' team members')
                ->line('• ' . ($plan?->getLimit('customers.max', 500)) . ' customers')
                ->line('• ' . ($plan?->getLimit('ai.requests.monthly', 100)) . ' AI requests / month')
                ->line('• ' . ($plan?->getLimit('widgets.max', 1)) . ' chat widgets');
        }

        return $message
            ->action('Open Dashboard', url('/dashboard'))
            ->line('Ready to explore? Any questions, just reply to this email.')
            ->salutation('Welcome aboard!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'welcome_trial',
            'subscription_id' => $this->subscription->id,
            'message'         => 'Welcome! Your free trial has started.',
        ];
    }
}
