<?php

namespace App\Notifications\Billing;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Payment $payment)
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
        return (new MailMessage)
            ->subject('Payment received — thank you!')
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . '!')
            ->line('We received your payment.')
            ->line('**Amount:** ' . $this->payment->currency . ' '
                . number_format((float) $this->payment->amount, 2))
            ->line('**Provider:** ' . ucfirst($this->payment->provider))
            ->action('View Billing', url('/billing'))
            ->line('Thank you for your business.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'payment_succeeded',
            'payment_id' => $this->payment->id,
            'message'    => 'Payment received: ' . $this->payment->currency . ' '
                . number_format((float) $this->payment->amount, 2),
        ];
    }
}
