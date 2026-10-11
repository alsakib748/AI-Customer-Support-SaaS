<?php

namespace App\Jobs\Billing;

use App\Models\BillingReminder;
use App\Models\Subscription;
use App\Notifications\Billing\RenewalReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRenewalReminders implements ShouldQueue
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
        Log::info('Starting renewal reminder processing');

        // Send 7 days before renewal
        $this->sendRemindersForDays(7);

        // Send 3 days before renewal
        $this->sendRemindersForDays(3);

        // Send 1 day before renewal
        $this->sendRemindersForDays(1);

        Log::info('Renewal reminder processing completed');
    }

    protected function sendRemindersForDays(int $days): void
    {
        $windowStart = now()->addDays($days)->startOfDay();
        $windowEnd   = now()->addDays($days)->endOfDay();

        $subscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('next_billing_at', [$windowStart, $windowEnd])
                    ->orWhere(function ($q) use ($windowStart, $windowEnd) {
                        // Fall back to the current period end when the provider
                        // has not populated next_billing_at.
                        $q->whereNull('next_billing_at')
                            ->whereBetween('current_period_ends_at', [$windowStart, $windowEnd]);
                    });
            })
            ->with(['tenant', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $periodAt  = $subscription->next_billing_at ?? $subscription->current_period_ends_at;
            $periodKey = $periodAt ? $periodAt->toDateString() : 'unknown';

            // Atomically claim the reminder so repeat runs never double-send.
            if (! BillingReminder::claim($subscription->tenant_id, $subscription->id, 'renewal', $days, $periodKey)) {
                Log::info('Renewal reminder already sent', [
                    'subscription_id' => $subscription->id,
                    'days_before'     => $days,
                    'period_key'      => $periodKey,
                ]);
                continue;
            }

            try {
                $owner = $subscription->tenant->getOwner();

                if ($owner) {
                    $owner->notify(new RenewalReminderNotification($subscription, $days));
                }

                Log::info('Renewal reminder sent', [
                    'subscription_id' => $subscription->id,
                    'days_before' => $days,
                ]);

            } catch (\Exception $e) {
                // Release the claim so a later run can retry delivery.
                BillingReminder::release($subscription->id, 'renewal', $days, $periodKey);

                Log::error('Failed to send renewal reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}