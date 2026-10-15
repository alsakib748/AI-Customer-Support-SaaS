<?php

namespace App\Notifications\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public ?Invoice $invoice = null,
        public ?int $daysPast = null,
        public bool $finalNotice = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $amount = $this->invoice?->formatted_total
            ?? ($plan ? ($plan->currency ?? '$').' '.number_format($plan->getPriceForCycle($this->subscription->billing_cycle), 2) : 'N/A');
        $subject = $this->finalNotice
            ? 'Final Payment Reminder: Account suspension imminent'
            : 'Payment Reminder: Invoice overdue';
        $dueText = $this->invoice?->due_at
            ? $this->invoice->due_at->format('M d, Y')
            : optional($this->subscription->ends_at)->format('M d, Y');

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.($notifiable->first_name ?? 'there').'!')
            ->line($this->finalNotice
                ? 'We still have not received payment for your most recent invoice. If payment is not received by the cutoff, your workspace will be suspended.'
                : 'Your subscription has an overdue invoice. Please make payment to avoid interruption.')
            ->line('**Invoice:** '.($this->invoice?->invoice_number ?? '—'))
            ->line('**Amount due:** '.$amount)
            ->when($dueText, fn ($m) => $m->line('**Due date:** '.$dueText))
            ->when($this->daysPast !== null, fn ($m) => $m->line('**Days overdue:** '.$this->daysPast))
            ->action('Pay Now', url('/billing/pay'))
            ->line('Thank you for your prompt attention.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_reminder',
            'subscription_id' => $this->subscription->id,
            'invoice_id' => $this->invoice?->id,
            'days_past' => $this->daysPast,
            'final_notice' => $this->finalNotice,
            'message' => $this->finalNotice
                ? 'Final reminder: account will be suspended if payment is not received.'
                : 'Payment reminder: invoice is overdue.',
        ];
    }
}
