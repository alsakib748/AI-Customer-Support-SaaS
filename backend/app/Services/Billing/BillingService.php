<?php

namespace App\Services\Billing;

use App\Models\BillingCustomer;
use App\Models\Plan;
use App\Models\PlanProviderPrice;
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

        if (! $price) {
            $price = $this->provisionPlanPrice($plan, $provider, $billingCycle);
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

    /**
     * Resolve or auto-create a provider price for a plan cycle.
     * Falls back to legacy plans.stripe_price_id_* columns first.
     */
    protected function provisionPlanPrice(Plan $plan, string $provider, string $billingCycle): PlanProviderPrice
    {
        $legacyId = $billingCycle === 'yearly'
            ? $plan->stripe_price_id_yearly
            : $plan->stripe_price_id_monthly;

        if ($legacyId) {
            return PlanProviderPrice::create([
                'plan_id'           => $plan->id,
                'provider'          => $provider,
                'billing_cycle'     => $billingCycle,
                'currency'          => $plan->currency,
                'provider_price_id' => $legacyId,
                'amount'            => $plan->getPriceForCycle($billingCycle),
                'is_active'         => true,
            ]);
        }

        $gateway = $this->gateways->driver($provider);

        $result = $gateway->createPlanPrice([
            'name'                => $plan->name . ' (' . ucfirst($billingCycle) . ')',
            'amount'              => $plan->getPriceForCycle($billingCycle),
            'currency'            => $plan->currency,
            'cycle'               => $billingCycle,
            'provider_product_id' => $plan->stripe_product_id,
            'metadata'            => [
                'plan_id'       => $plan->id,
                'plan_slug'     => $plan->slug,
                'billing_cycle' => $billingCycle,
            ],
        ]);

        if (empty($plan->stripe_product_id) && !empty($result['provider_product_id'])) {
            Plan::where('id', $plan->id)->update(['stripe_product_id' => $result['provider_product_id']]);
        }

        return PlanProviderPrice::create([
            'plan_id'           => $plan->id,
            'provider'          => $provider,
            'billing_cycle'     => $billingCycle,
            'currency'          => $plan->currency,
            'provider_price_id' => $result['provider_price_id'],
            'amount'            => $plan->getPriceForCycle($billingCycle),
            'is_active'         => true,
            'metadata'          => ['auto_provisioned' => true],
        ]);
    }
}
