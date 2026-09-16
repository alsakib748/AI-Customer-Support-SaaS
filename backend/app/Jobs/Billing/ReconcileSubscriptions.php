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
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $manager): void
    {
        Log::info('Starting subscription reconciliation');

        // Only reconcile active subscriptions with gateway IDs
        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->chunk(100, function ($subs) {
                foreach ($subs as $sub) {
                    try {
                        // Reconcile with Stripe
                        $this->reconcileStripe($sub);
                    } catch (\Exception $e) {
                        Log::error('Failed to reconcile subscription', [
                            'subscription_id' => $sub->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('Subscription reconciliation completed');
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