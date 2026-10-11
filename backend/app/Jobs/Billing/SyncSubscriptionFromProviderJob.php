<?php

namespace App\Jobs\Billing;

use App\Models\PlanProviderPrice;
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

            $previousStatus = $subscription->status;

            $updates = ['status' => $this->mapStatus($remote['status'] ?? null)];

            if (!empty($remote['current_period_start'])) {
                $updates['current_period_starts_at'] = \Carbon\Carbon::createFromTimestamp(
                    (int) $remote['current_period_start']
                );
            }

            if (!empty($remote['current_period_end'])) {
                $endsAt = \Carbon\Carbon::createFromTimestamp((int) $remote['current_period_end']);
                $updates['current_period_ends_at'] = $endsAt;
                $updates['next_billing_at']       = $endsAt;
                $updates['ends_at']               = $endsAt;
            }

            if (array_key_exists('cancel_at_period_end', $remote)) {
                $updates['cancel_at_period_end'] = (bool) $remote['cancel_at_period_end'];
            }

            if (!empty($remote['trial_start'])) {
                $updates['trial_starts_at'] = \Carbon\Carbon::createFromTimestamp((int) $remote['trial_start']);
            }

            if (!empty($remote['trial_end'])) {
                $updates['trial_ends_at'] = \Carbon\Carbon::createFromTimestamp((int) $remote['trial_end']);
            }

            if (!empty($remote['canceled_at'])) {
                $updates['cancelled_at'] = \Carbon\Carbon::createFromTimestamp((int) $remote['canceled_at']);
            }

            // Keep the local plan in sync when the provider reports a different price.
            $remotePriceId = data_get($remote, 'items.data.0.price.id');

            if ($remotePriceId && $remotePriceId !== $subscription->provider_price_id) {
                $providerPrice = PlanProviderPrice::query()
                    ->where('provider', $subscription->provider)
                    ->where('provider_price_id', $remotePriceId)
                    ->first();

                if ($providerPrice) {
                    $updates['provider_price_id'] = $remotePriceId;

                    if ((int) $providerPrice->plan_id !== (int) $subscription->plan_id) {
                        $updates['plan_id'] = $providerPrice->plan_id;
                    }
                } else {
                    Log::warning('Synced subscription references an unknown provider price', [
                        'subscription_id' => $subscription->id,
                        'provider'        => $subscription->provider,
                        'provider_price_id' => $remotePriceId,
                    ]);
                }
            }

            $changed = $this->onlyChanged($subscription, $updates);

            if (empty($changed)) {
                return;
            }

            $subscription->update($updates);

            Log::info('Subscription synced from provider', [
                'subscription_id' => $subscription->id,
                'old_status'      => $previousStatus,
                'new_status'      => $updates['status'] ?? $previousStatus,
                'changed'         => array_keys($changed),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to sync subscription', [
                'subscription_id' => $this->subscriptionId,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Drop no-op values so a re-run never writes identical data.
     *
     * @return array<string, mixed>
     */
    protected function onlyChanged(Subscription $subscription, array $updates): array
    {
        $changed = [];

        foreach ($updates as $key => $value) {
            $current = $subscription->getAttribute($key);

            $equal = $value instanceof \DateTimeInterface
                ? (optional($current)?->getTimestamp() === $value->getTimestamp())
                : $current == $value;

            if (! $equal) {
                $changed[$key] = $value;
            }
        }

        return $changed;
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
