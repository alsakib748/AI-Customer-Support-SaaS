<?php

namespace Tests\Feature\Billing;

use App\Http\Middleware\CheckSubscription;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class EntitlementGraceTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        config(['billing.grace_period.past_due_days' => 3]);
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

    protected function subscription(array $attributes): Subscription
    {
        $plan = $this->createDefaultPlan([
            'name' => 'Pro',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 0,
            'limits' => ['agents.max' => 5],
        ]);

        return Subscription::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ], $attributes));
    }

    public function test_past_due_within_grace_still_entitles(): void
    {
        $this->subscription([
            'status' => 'past_due',
            'current_period_ends_at' => now()->subDay(),
        ]);

        $service = app(SubscriptionService::class);

        $this->assertNotNull($service->getEntitlingSubscription($this->tenant->id));
        $this->assertTrue($service->getSubscription($this->tenant->id)->grantsAccess());
        $this->assertTrue(app(BillingLimitService::class)->check($this->tenant, 'agents.max')['allowed']);
    }

    public function test_past_due_beyond_grace_does_not_entitle(): void
    {
        $this->subscription([
            'status' => 'past_due',
            'current_period_ends_at' => now()->subDays(10),
        ]);

        $service = app(SubscriptionService::class);

        $this->assertNull($service->getEntitlingSubscription($this->tenant->id));

        $result = app(BillingLimitService::class)->check($this->tenant, 'agents.max');
        $this->assertFalse($result['allowed']);
        $this->assertSame('no_active_subscription', $result['code']);
    }

    public function test_cancelled_but_paid_through_still_entitles(): void
    {
        $this->subscription([
            'status' => 'cancelled',
            'cancel_at_period_end' => true,
            'ends_at' => now()->addDays(5),
        ]);

        $subscription = app(SubscriptionService::class)->getEntitlingSubscription($this->tenant->id);

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->grantsAccess());
    }

    public function test_cancelled_and_lapsed_does_not_entitle(): void
    {
        $this->subscription([
            'status' => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);

        $this->assertNull(
            app(SubscriptionService::class)->getEntitlingSubscription($this->tenant->id)
        );
    }

    public function test_middleware_blocks_without_entitling_subscription(): void
    {
        $middleware = app(CheckSubscription::class);
        $request = Request::create('/api/v1/customers', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(402, $response->getStatusCode());
        $this->assertSame('no_subscription', $response->getData(true)['code']);
    }

    public function test_middleware_allows_with_entitling_subscription(): void
    {
        $this->subscription(['status' => 'active']);

        $middleware = app(CheckSubscription::class);
        $request = Request::create('/api/v1/customers', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($request->attributes->get('subscription'));
    }
}
