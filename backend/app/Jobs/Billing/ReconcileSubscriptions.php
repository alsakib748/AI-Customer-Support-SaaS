<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Subscription::whereNotNull('provider_subscription_id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->chunk(100, function ($subs) {
                foreach ($subs as $sub) {
                    SyncSubscriptionFromProviderJob::dispatch($sub->id)->onQueue('billing');
                }
            });

        Log::info('Subscription reconciliation queued');
    }
}
