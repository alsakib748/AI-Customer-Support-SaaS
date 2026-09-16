<?php

namespace App\Notifications\Billing;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Invoice $invoice,
        public string $reason
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
            ->subject('Payment Failed - Action Required')
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('We were unable to process your payment.')
            ->line('**Invoice:** ' . $this->invoice->invoice_number)
            ->line('**Amount:** ' . $this->invoice->formatted_total)
            ->line('**Reason:** ' . $this->reason)
            ->action('Update Payment Method', url('/billing/payment-method'))
            ->line('Please update your payment information to continue using our service.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'invoice_id' => $this->invoice->id,
            'reason' => $this->reason,
            'message' => 'Payment failed: ' . $this->reason,
        ];
    }
}