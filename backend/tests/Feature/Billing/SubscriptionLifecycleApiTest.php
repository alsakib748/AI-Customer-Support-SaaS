<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithBillingDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * HTTP-level coverage of the subscription lifecycle endpoints:
 * current / store / cancel / resume / validate-coupon / providers.
 *
 * Billing models are central; the tenant connection is still isolated by the
 * harness so no tenant-owned data leaks between tests.
 */
class SubscriptionLifecycleApiTest extends TestCase
{
    use InteractsWithBillingDomain;
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();

        // Billing is central-scoped: `exists:plans,id` and the billing models
        // must resolve against the central connection, not the tenant one.
        config(['database.default' => 'central']);
        DB::purge('central');

        $this->createCentralSchema();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    protected function freePlan(array $overrides = []): Plan
    {
        return $this->newPlan(array_merge([
            'price_monthly' => 0,
            'price_yearly' => 0,
            'trial_days' => 0,
        ], $overrides));
    }

    protected function paidPlan(array $overrides = []): Plan
    {
        return $this->newPlan(array_merge([
            'name' => 'Pro',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 0,
        ], $overrides));
    }

    public function test_current_returns_the_active_subscription(): void
    {
        [$tenant, , $subscription] = $this->bootBillingWorkspace();

        $this->getJson('/api/v1/subscription/current')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_current_returns_null_when_the_tenant_has_no_subscription(): void
    {
        [, , $subscription] = $this->bootBillingWorkspace(withSubscription: false);

        $this->assertNull($subscription);

        $this->getJson('/api/v1/subscription/current')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_store_creates_a_free_subscription(): void
    {
        [$tenant] = $this->bootBillingWorkspace(withSubscription: false);
        $plan = $this->freePlan();

        $this->postJson('/api/v1/subscription', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(
            1,
            Subscription::where('tenant_id', $tenant->id)->count(),
            'a free plan may be created directly'
        );
    }

    public function test_store_rejects_a_paid_plan_with_checkout_required(): void
    {
        [$tenant] = $this->bootBillingWorkspace(withSubscription: false);
        $plan = $this->paidPlan();

        $this->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'checkout_required');

        $this->assertSame(0, Subscription::where('tenant_id', $tenant->id)->count());
    }

    public function test_store_rejects_a_tenant_that_already_has_an_active_subscription(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $plan = $this->freePlan();

        $this->postJson('/api/v1/subscription', ['plan_id' => $plan->id])
            ->assertStatus(400);

        $this->assertSame(1, Subscription::where('tenant_id', $tenant->id)->count());
    }

    public function test_store_requires_a_valid_plan(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);

        $this->postJson('/api/v1/subscription', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['plan_id']]);

        $this->postJson('/api/v1/subscription', ['plan_id' => 999999])
            ->assertStatus(422);
    }

    public function test_cancel_defaults_to_end_of_period_and_keeps_access(): void
    {
        [$tenant] = $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/cancel', ['reason' => 'too expensive'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        $this->assertSame('active', $subscription->status, 'a period-end cancel keeps the subscription active');
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertSame('too expensive', $subscription->metadata['cancellation_reason'] ?? null);
    }

    public function test_cancel_immediately_marks_the_subscription_cancelled(): void
    {
        [$tenant] = $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/cancel', ['immediately' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', Subscription::where('tenant_id', $tenant->id)->first()->status);
    }

    public function test_cancel_returns_404_without_an_active_subscription(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);

        $this->postJson('/api/v1/subscription/cancel', [])
            ->assertStatus(404);
    }

    public function test_resume_rejects_a_subscription_that_is_not_cancelled(): void
    {
        $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/resume', [])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /**
     * Known defect (captured, not fixed): `SubscriptionService::resume()` guards
     * on `$subscription->canCancel()`, which is already false for a cancelled
     * subscription (`! is_cancelled`), so it always throws and the endpoint
     * returns a 500 instead of reactivating the plan.
     */
    public function test_resume_of_a_cancelled_subscription_currently_returns_a_server_error(): void
    {
        [$tenant] = $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/cancel', ['immediately' => true])->assertStatus(200);

        $this->postJson('/api/v1/subscription/resume', [])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertSame('cancelled', Subscription::where('tenant_id', $tenant->id)->first()->status);
    }

    public function test_validate_coupon_reports_an_unknown_code_as_invalid(): void
    {
        $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/validate-coupon', ['code' => 'DOES-NOT-EXIST'])
            ->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);
    }

    /**
     * Known defect (captured, not fixed): `validateCoupon()` runs validation
     * inside a try block that only catches `\Exception`, and Laravel's
     * `ValidationException` extends `\Exception`, so a missing `code` is
     * reported as a 500 instead of a 422.
     */
    public function test_validate_coupon_without_a_code_currently_returns_a_server_error(): void
    {
        $this->bootBillingWorkspace();

        $this->postJson('/api/v1/subscription/validate-coupon', [])
            ->assertStatus(500);
    }

    public function test_providers_endpoint_lists_available_gateways(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);

        $response = $this->getJson('/api/v1/subscription/providers')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['key', 'name', 'available']]]);

        $keys = array_column($response->json('data'), 'key');
        $this->assertContains('stripe', $keys);
        $this->assertContains('paypal', $keys);
    }

    public function test_upgrade_rejects_a_plan_that_is_not_an_upgrade(): void
    {
        $this->bootBillingWorkspace();
        $sameTier = $this->freePlan(['name' => 'Another Free']);

        $this->postJson('/api/v1/subscription/upgrade', ['plan_id' => $sameTier->id])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_downgrade_rejects_a_plan_that_is_not_a_downgrade(): void
    {
        $this->bootBillingWorkspace();
        $sameTier = $this->freePlan(['name' => 'Another Free']);

        $this->postJson('/api/v1/subscription/downgrade', ['plan_id' => $sameTier->id])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_upgrade_returns_404_without_an_active_subscription(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);
        $plan = $this->paidPlan();

        $this->postJson('/api/v1/subscription/upgrade', ['plan_id' => $plan->id])
            ->assertStatus(404);
    }

    public function test_checkout_requires_a_plan(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);

        $this->postJson('/api/v1/subscription/checkout', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['plan_id']]);

        $this->assertSame(0, Subscription::where('status', 'pending')->count());
    }

    public function test_checkout_creates_a_pending_subscription_for_a_paid_plan(): void
    {
        [$tenant] = $this->bootBillingWorkspace(withSubscription: false);
        $plan = $this->paidPlan();

        $this->mock(BillingService::class, function ($mock) {
            $mock->shouldReceive('createCheckout')->once()->andReturn([
                'checkout_url' => 'https://checkout.test/session/abc',
                'session_id' => 'cs_test_123',
                'provider_subscription_id' => null,
            ]);
        });

        $this->postJson('/api/v1/subscription/checkout', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'provider' => 'stripe',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.test/session/abc')
            ->assertJsonPath('data.provider', 'stripe');

        $this->assertSame(1, Subscription::where('tenant_id', $tenant->id)->where('status', 'pending')->count());
    }

    public function test_checkout_verify_requires_a_session_id(): void
    {
        $this->bootBillingWorkspace(withSubscription: false);

        $this->postJson('/api/v1/subscription/checkout/verify', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['session_id']]);
    }
}
