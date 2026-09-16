<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentFailed;
use App\Notifications\Billing\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentFailedNotification
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
    public function handle(PaymentFailed $event): void
    {
        try {
            $owner = $event->payment->tenant->getOwner();
            if ($owner && $event->payment->invoice) {
                $owner->notify(new PaymentFailedNotification(
                    $event->payment->invoice,
                    $event->reason
                ));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed notification', ['error' => $e->getMessage()]);
        }
    }
}