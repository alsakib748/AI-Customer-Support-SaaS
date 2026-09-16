<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionCreated;
use App\Notifications\Billing\SubscriptionCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubscriptionCreatedNotification
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
    public function handle(SubscriptionCreated $event): void
    {
        try {
            $owner = $event->subscription->tenant->getOwner();

            if ($owner) {
                $owner->notify(new SubscriptionCreatedNotification($event->subscription));
            }

            Log::info('Subscription created notification sent', [
                'subscription_id' => $event->subscription->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send subscription created notification', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}