<?php

namespace App\Notifications\Billing;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaidNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Invoice $invoice)
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
            ->subject('Payment Received - ' . $this->invoice->invoice_number)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('We have received your payment. Thank you!')
            ->line('**Invoice:** ' . $this->invoice->invoice_number)
            ->line('**Amount:** ' . $this->invoice->formatted_total)
            ->line('**Paid At:** ' . $this->invoice->paid_at->format('M d, Y H:i'))
            ->action('View Invoice', url('/billing/invoices/' . $this->invoice->id))
            ->line('Thank you for your business!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_paid',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->total,
            'message' => 'Payment received for invoice ' . $this->invoice->invoice_number,
        ];
    }
}