<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
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
        $subscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->whereBetween('next_billing_at', [
                now()->addDays($days)->startOfDay(),
                now()->addDays($days)->endOfDay(),
            ])
            ->with(['tenant', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
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
                Log::error('Failed to send renewal reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}