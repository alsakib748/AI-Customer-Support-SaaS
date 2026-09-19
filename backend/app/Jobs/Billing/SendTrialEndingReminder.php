<?php

namespace App\Jobs\Billing;

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
                try {
                    $owner = $subscription->tenant->getOwner();
                    if ($owner) {
                        $owner->notify(new TrialEndingNotification($subscription, $daysBefore));
                    }
                } catch (\Exception $e) {
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
