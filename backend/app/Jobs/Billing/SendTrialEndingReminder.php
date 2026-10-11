<?php

namespace App\Jobs\Billing;

use App\Models\BillingReminder;
use App\Models\Subscription;
use App\Notifications\Billing\TrialEndingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTrialEndingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting trial ending reminder processing');

        foreach ([7, 3, 1] as $daysBefore) {
            $subscriptions = Subscription::trialing()
                ->whereBetween('trial_ends_at', [
                    now()->addDays($daysBefore)->startOfDay(),
                    now()->addDays($daysBefore)->endOfDay(),
                ])
                ->with(['tenant', 'plan'])
                ->get();

            foreach ($subscriptions as $subscription) {
                $periodKey = $subscription->trial_ends_at
                    ? $subscription->trial_ends_at->toDateString()
                    : 'unknown';

                // Atomically claim the reminder so repeat runs never double-send.
                if (! BillingReminder::claim($subscription->tenant_id, $subscription->id, 'trial', $daysBefore, $periodKey)) {
                    Log::info('Trial ending reminder already sent', [
                        'subscription_id' => $subscription->id,
                        'days_before'     => $daysBefore,
                        'period_key'      => $periodKey,
                    ]);
                    continue;
                }

                try {
                    $owner = $subscription->tenant->getOwner();
                    if ($owner) {
                        $owner->notify(new TrialEndingNotification($subscription, $daysBefore));
                    }
                } catch (\Exception $e) {
                    // Release the claim so a later run can retry delivery.
                    BillingReminder::release($subscription->id, 'trial', $daysBefore, $periodKey);

                    Log::error('Failed to send trial reminder', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Trial ending reminder processing completed');
    }
}
