<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionUpgraded;
use App\Notifications\Billing\SubscriptionUpgradedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSubscriptionUpgradedNotification
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
    public function handle(SubscriptionUpgraded $event): void
    {
        try {
            $owner = $event->subscription->tenant->getOwner();
            if ($owner) {
                $owner->notify(new SubscriptionUpgradedNotification(
                    $event->subscription,
                    $event->oldPlan,
                    $event->newPlan
                ));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send upgrade notification', ['error' => $e->getMessage()]);
        }
    }
}