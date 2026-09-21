<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
use App\Payments\PaymentGatewayManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionFromProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

     public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $subscriptionId) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $gateways): void
    {
        $subscription = Subscription::find($this->subscriptionId);

        if (!$subscription || !$subscription->isProviderManaged()) {
            return;
        }

        try {
            $gateway = $gateways->driver($subscription->provider);
            $remote = $gateway->getSubscription($subscription->provider_subscription_id);

            $remoteStatus = $remote['status'] ?? null;
            $localStatus = $this->mapStatus($remoteStatus);

            $updates = ['status' => $localStatus];

            if (!empty($remote['current_period_start'])) {
                $updates['current_period_starts_at'] = \Carbon\Carbon::createFromTimestamp(
                    $remote['current_period_start']
                );
            }
            if (!empty($remote['current_period_end'])) {
                $updates['current_period_ends_at'] = \Carbon\Carbon::createFromTimestamp(
                    $remote['current_period_end']
                );
                $updates['next_billing_at'] = $updates['current_period_ends_at'];
            }

            $subscription->update($updates);

            Log::info('Subscription synced from provider', [
                'subscription_id' => $subscription->id,
                'old_status'      => $subscription->getOriginal('status'),
                'new_status'      => $localStatus,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to sync subscription', [
                'subscription_id' => $this->subscriptionId,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function mapStatus(?string $remoteStatus): string
    {
        return match ($remoteStatus) {
            'active', 'ACTIVE'      => 'active',
            'trialing', 'TRIALING'  => 'trialing',
            'past_due', 'PAST_DUE', 'SUSPENDED' => 'past_due',
            'canceled', 'CANCELLED', 'EXPIRED' => 'cancelled',
            'paused'                => 'paused',
            default                 => 'active',
        };
    }
}
