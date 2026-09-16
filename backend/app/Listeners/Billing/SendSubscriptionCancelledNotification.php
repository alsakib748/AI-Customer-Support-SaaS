<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionCancelled;
use App\Notifications\Billing\SubscriptionCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubscriptionCancelledNotification
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
    public function handle(SubscriptionCancelled $event): void
    {
        try {
            $owner = $event->subscription->tenant->getOwner();

            if ($owner) {
                $owner->notify(new SubscriptionCancelledNotification(
                    $event->subscription,
                    $event->immediately
                ));
            }

            Log::info('Subscription cancelled notification sent', [
                'subscription_id' => $event->subscription->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send subscription cancelled notification', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}