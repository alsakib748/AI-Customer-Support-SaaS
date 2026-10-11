<?php

namespace Tests\Feature\Billing;

use App\Jobs\Billing\SyncSubscriptionFromProviderJob;
use App\Models\Plan;
use App\Models\PlanProviderPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class SubscriptionSyncTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralSchema();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);
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

    protected function subscription(Plan $plan, string $priceId, array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_sync_1',
            'provider_customer_id' => 'cus_sync_1',
            'provider_price_id' => $priceId,
        ], $attributes));
    }

    protected function sync(array $remote, Subscription $subscription): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('getSubscription')->once()->with('sub_sync_1')->andReturn($remote);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->with('stripe')->andReturn($gateway);

        (new SyncSubscriptionFromProviderJob($subscription->id))->handle($manager);
    }

    public function test_sync_switches_plan_when_provider_price_changes(): void
    {
        $basic = $this->plan('Basic', 10);
        $pro   = $this->plan('Pro', 30);

        PlanProviderPrice::create([
            'plan_id' => $pro->id,
            'provider' => 'stripe',
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'provider_price_id' => 'price_pro',
            'amount' => 30,
            'is_active' => true,
        ]);

        $subscription = $this->subscription($basic, 'price_basic');

        $this->sync([
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => true,
            'items' => ['data' => [['price' => ['id' => 'price_pro']]]],
        ], $subscription);

        $subscription->refresh();
        $this->assertSame($pro->id, $subscription->plan_id);
        $this->assertSame('price_pro', $subscription->provider_price_id);
        $this->assertTrue((bool) $subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_sync_applies_trial_dates(): void
    {
        $plan = $this->plan('Trial', 20);
        $subscription = $this->subscription($plan, 'price_trial');

        $this->sync([
            'status' => 'trialing',
            'trial_start' => now()->timestamp,
            'trial_end' => now()->addDays(14)->timestamp,
            'items' => ['data' => [['price' => ['id' => 'price_trial']]]],
        ], $subscription);

        $subscription->refresh();
        $this->assertSame('trialing', $subscription->status);
        $this->assertNotNull($subscription->trial_starts_at);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    public function test_sync_does_not_write_when_nothing_changed(): void
    {
        Log::spy();

        $plan = $this->plan('Stable', 20);
        $periodStart = now()->subDays(5);
        $periodEnd = now()->addDays(25);

        $subscription = $this->subscription($plan, 'price_stable', [
            'current_period_starts_at' => $periodStart,
            'current_period_ends_at' => $periodEnd,
            'next_billing_at' => $periodEnd,
            'ends_at' => $periodEnd,
        ]);

        $this->sync([
            'status' => 'active',
            'current_period_start' => $periodStart->timestamp,
            'current_period_end' => $periodEnd->timestamp,
            'cancel_at_period_end' => false,
            'items' => ['data' => [['price' => ['id' => 'price_stable']]]],
        ], $subscription);

        Log::shouldNotHaveReceived('info', ['Subscription synced from provider']);
    }
}
