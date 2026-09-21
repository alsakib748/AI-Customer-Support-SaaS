<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentSucceeded;
use App\Notifications\Billing\PaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentSucceededNotification
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
    public function handle(PaymentSucceeded $event): void
    {
        try {
            $owner = $event->payment->tenant?->getOwner();
            if ($owner) {
                $owner->notify(new PaymentSucceededNotification($event->payment));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment succeeded notification', [
                'payment_id' => $event->payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    
    }
}
