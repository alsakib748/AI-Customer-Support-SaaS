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

    protected function reconcile(Subscription $subscription): void
    {
        // Gateway-specific reconciliation lives in the respective Gateway class.
        // The gateway layer will implement a `reconcileSubscription()` method.
        //
        // $gateway = app(PaymentGatewayManager::class)->gateway($subscription->provider);
        // $remote = $gateway->fetchSubscription($subscription->provider_subscription_id);
        // ... map status back

        Log::debug('Reconcile placeholder', ['subscription_id' => $subscription->id]);
    }

    protected function reconcileStripe(Subscription $subscription): void
    {
        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $stripeSub = $stripe->subscriptions->retrieve($subscription->stripe_subscription_id);

            // Sync status
            $mapStatus = [
                'active' => 'active',
                'past_due' => 'past_due',
                'canceled' => 'cancelled',
                'unpaid' => 'past_due',
                'trialing' => 'trialing',
                'incomplete' => 'past_due',
                'incomplete_expired' => 'expired',
                'paused' => 'paused',
            ];

            $newStatus = $mapStatus[$stripeSub->status] ?? $subscription->status;

            if ($newStatus !== $subscription->status) {
                $subscription->update(['status' => $newStatus]);
            }

            // Sync period end
            if ($stripeSub->current_period_end) {
                $subscription->update([
                    'ends_at' => \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end),
                    'next_billing_at' => \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Stripe reconciliation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
