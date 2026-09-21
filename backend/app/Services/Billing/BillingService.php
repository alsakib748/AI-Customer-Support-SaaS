<?php

namespace App\Services\Billing;

use App\Models\BillingCustomer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected SubscriptionService $subscriptions,
    ) {}

    /**
     * Create or reuse a provider customer for this tenant.
     */
    public function ensureProviderCustomer(Tenant $tenant, string $provider): BillingCustomer
    {
        $existing = BillingCustomer::where('tenant_id', $tenant->id)
            ->where('provider', $provider)
            ->first();

        if ($existing) {
            return $existing;
        }

        $owner = $tenant->getOwner();
        $gateway = $this->gateways->driver($provider);

        $result = $gateway->createCustomer([
            'email'    => $owner?->email,
            'name'     => $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
            ],
        ]);

        return BillingCustomer::create([
            'tenant_id'           => $tenant->id,
            'provider'            => $provider,
            'provider_customer_id'=> $result['provider_customer_id'],
            'email'               => $owner?->email,
            'metadata'            => $result['raw'] ?? [],
        ]);
    }

    /**
     * Create a checkout session for a tenant subscribing to a plan.
     */
    public function createCheckout(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        ?string $provider = null,
    ): array {
        $provider = $provider ?: config('payment.default');

        if (!$this->gateways->has($provider)) {
            throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }

        if (!$plan->is_active) {
            throw new \RuntimeException('This plan is not available for purchase.');
        }

        $price = $plan->providerPrice($provider, $billingCycle, $plan->currency);

        if (!$price) {
            throw new \RuntimeException("No price configured for {$provider} on this plan.");
        }

        $billingCustomer = $this->ensureProviderCustomer($tenant, $provider);
        $gateway = $this->gateways->driver($provider);

        return $gateway->createCheckout([
            'mode'                 => 'subscription',
            'provider_customer_id' => $billingCustomer->provider_customer_id,
            'provider_price_id'    => $price->provider_price_id,
            'trial_days'           => $plan->trial_days > 0 ? $plan->trial_days : null,
            'metadata'             => [
                'tenant_id'      => $tenant->id,
                'plan_id'        => $plan->id,
                'billing_cycle'  => $billingCycle,
            ],
        ]);
    }
}
