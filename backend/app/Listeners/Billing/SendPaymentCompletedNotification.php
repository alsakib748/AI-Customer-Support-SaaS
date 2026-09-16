<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentCompleted;
use App\Notifications\Billing\InvoicePaidNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentCompletedNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentCompleted $event): void
    {
        try {
            if (!$event->payment->invoice)
                return;

            $owner = $event->payment->tenant->getOwner();
            if ($owner) {
                $owner->notify(new InvoicePaidNotification($event->payment->invoice));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send payment completed notification', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}