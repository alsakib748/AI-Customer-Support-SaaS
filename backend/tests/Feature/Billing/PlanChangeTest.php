<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class PlanChangeTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        config(['payment.default' => 'stripe']);
        DB::purge('central');

        $this->createCentralSchema();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        app()->instance('current_tenant', $this->tenant);
    }

    protected function plan(string $name, float $monthly): Plan
    {
        return $this->createDefaultPlan([
            'name' => $name,
            'slug' => strtolower($name) . uniqid(),
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'currency' => 'USD',
            'trial_days' => 0,
        ]);
    }

    protected function activeSubscription(Plan $plan, array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'provider' => null,
            'provider_subscription_id' => null,
            'provider_price_id' => null,
        ], $attributes));
    }

    protected function providerSub(Plan $plan, string $priceId): Subscription
    {
        return $this->activeSubscription($plan, [
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_123',
            'provider_price_id' => $priceId,
        ]);
    }

    protected function controllerWithGateway(PaymentGateway $gateway): SubscriptionController
    {
        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('has')->andReturn(true);
        $manager->shouldReceive('driver')->andReturn($gateway);

        app()->instance(PaymentGatewayManager::class, $manager);

        return app(SubscriptionController::class);
    }

    protected function addProviderPrice(Plan $plan, string $priceId, string $cycle = 'monthly'): void
    {
        $plan->providerPrices()->create([
            'provider' => 'stripe',
            'billing_cycle' => $cycle,
            'currency' => 'USD',
            'provider_price_id' => $priceId,
            'amount' => $plan->price_monthly,
            'is_active' => true,
        ]);
    }

    protected function planRequest(int $planId): Request
    {
        return Request::create('/', 'POST', ['plan_id' => $planId]);
    }

    public function test_local_upgrade_requires_checkout(): void
    {
        $current = $this->plan('Basic', 10);
        $target  = $this->plan('Pro', 30);
        $this->activeSubscription($current);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')->never();

        $response = $this->controllerWithGateway($gateway)
            ->upgrade($this->planRequest($target->id));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('checkout_required', $response->getData(true)['code']);
        $this->assertSame($current->id, Subscription::first()->plan_id);
    }

    public function test_provider_upgrade_changes_provider_then_switches_plan(): void
    {
        $current = $this->plan('Basic', 10);
        $target  = $this->plan('Pro', 30);
        $this->addProviderPrice($target, 'price_pro');
        $this->providerSub($current, 'price_basic');

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')
            ->once()
            ->with('sub_123', Mockery::on(fn ($data) => $data['provider_price_id'] === 'price_pro'
                && $data['proration_behavior'] === 'create_prorations'))
            ->andReturn(['status' => 'active']);

        $response = $this->controllerWithGateway($gateway)
            ->upgrade($this->planRequest($target->id));

        $this->assertSame(200, $response->response()->getStatusCode());

        $subscription = Subscription::first();
        $this->assertSame($target->id, $subscription->plan_id);
        $this->assertSame('price_pro', $subscription->provider_price_id);
        $this->assertSame(0, Invoice::count(), 'the provider issues the proration invoice, not us');
    }

    public function test_provider_upgrade_fails_without_a_provider_price(): void
    {
        $current = $this->plan('Basic', 10);
        $target  = $this->plan('Pro', 30);
        $this->providerSub($current, 'price_basic');

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')->never();

        $response = $this->controllerWithGateway($gateway)
            ->upgrade($this->planRequest($target->id));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('provider_price_missing', $response->getData(true)['code']);
        $this->assertSame($current->id, Subscription::first()->plan_id);
    }

    public function test_provider_change_failure_does_not_switch_plan(): void
    {
        Log::spy();

        $current = $this->plan('Basic', 10);
        $target  = $this->plan('Pro', 30);
        $this->addProviderPrice($target, 'price_pro');
        $this->providerSub($current, 'price_basic');

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')->once()->andThrow(new \RuntimeException('boom'));

        $response = $this->controllerWithGateway($gateway)
            ->upgrade($this->planRequest($target->id));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('provider_change_failed', $response->getData(true)['code']);
        $this->assertSame($current->id, Subscription::first()->plan_id);
    }

    public function test_downgrade_registers_lower_price_with_provider(): void
    {
        $current = $this->plan('Pro', 30);
        $mid     = $this->plan('Mid', 20);
        $this->addProviderPrice($mid, 'price_mid');
        $this->providerSub($current, 'price_pro');

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')
            ->once()
            ->with('sub_123', Mockery::on(fn ($data) => $data['provider_price_id'] === 'price_mid'
                && $data['proration_behavior'] === 'none'))
            ->andReturn(['status' => 'active']);

        $response = $this->controllerWithGateway($gateway)
            ->downgrade($this->planRequest($mid->id));

        $this->assertSame(200, $response->response()->getStatusCode());

        $subscription = Subscription::first();
        $this->assertSame($current->id, $subscription->plan_id, 'plan switch is deferred to period end');
        $this->assertSame('price_mid', $subscription->provider_price_id);
        $this->assertSame($mid->id, data_get($subscription->metadata, 'pending_plan_id'));
    }

    public function test_downgrade_to_free_requires_cancellation(): void
    {
        $current = $this->plan('Pro', 30);
        $free    = $this->plan('Free', 0);
        $this->providerSub($current, 'price_pro');

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('changeSubscription')->never();

        $response = $this->controllerWithGateway($gateway)
            ->downgrade($this->planRequest($free->id));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('cancel_required', $response->getData(true)['code']);
        $this->assertSame($current->id, Subscription::first()->plan_id);
    }
}
