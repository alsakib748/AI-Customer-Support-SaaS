<?php

namespace App\Listeners\Billing;

use App\Events\Billing\TrialEndingSoon;
use App\Notifications\Billing\TrialEndingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTrialEndingNotification
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
    public function handle(TrialEndingSoon $event): void
    {
        try {
            $owner = $event->subscription->tenant?->getOwner();

            if ($owner) {
                $owner->notify(new TrialEndingNotification(
                    $event->subscription,
                    $event->daysRemaining,
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send trial ending notification', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}