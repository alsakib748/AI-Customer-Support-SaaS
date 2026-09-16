<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionRenewed;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubscriptionRenewedNotification
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
    public function handle(SubscriptionRenewed $event): void
    {
        try {
            $owner = $event->subscription->tenant->getOwner();
            if ($owner) {
                $owner->notify(new SubscriptionRenewedNotification($event->subscription));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send renewal notification', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}