<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionExpired;
use App\Notifications\Billing\SubscriptionExpiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubscriptionExpiredNotification
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
    public function handle(SubscriptionExpired $event): void
    {
        try {
            $owner = $event->subscription->tenant->getOwner();
            if ($owner) {
                $owner->notify(new SubscriptionExpiredNotification($event->subscription));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send expiration notification', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}