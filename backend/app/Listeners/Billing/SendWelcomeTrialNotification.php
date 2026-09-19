<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionCreated;
use App\Notifications\Billing\WelcomeTrialNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendWelcomeTrialNotification
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
            $owner = $event->subscription->tenant?->getOwner();
            if ($owner) {
                $owner->notify(new WelcomeTrialNotification($event->subscription));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome trial notification', [
                'subscription_id' => $event->subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }

    }
}
