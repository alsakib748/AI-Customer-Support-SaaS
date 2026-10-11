<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
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

        app()->instance('current_tenant', $this->tenant);
    }

    protected function paidPlan(): Plan
    {
        return $this->createDefaultPlan([
            'name' => 'Pro',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 0,
        ]);
    }

    protected function freePlan(): Plan
    {
        return $this->createDefaultPlan([
            'name' => 'Free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'trial_days' => 0,
        ]);
    }

    public function test_store_rejects_a_paid_plan_without_checkout(): void
    {
        $plan = $this->paidPlan();

        $request = Request::create('/', 'POST', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response = app(SubscriptionController::class)->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('checkout_required', $response->getData(true)['code']);

        $this->assertSame(
            0,
            Subscription::where('tenant_id', $this->tenant->id)->count(),
            'a paid plan must never be activated from client-supplied data'
        );
        $this->assertSame(0, Payment::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_store_allows_a_free_plan(): void
    {
        $plan = $this->freePlan();

        $request = Request::create('/', 'POST', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response = app(SubscriptionController::class)->store($request);

        $this->assertSame(201, $response->response()->getStatusCode());

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($subscription, 'a free plan may be created directly');
        $this->assertSame('active', $subscription->status);
    }

    public function test_create_subscription_service_refuses_paid_plans_by_default(): void
    {
        $plan = $this->paidPlan();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Paid plans must be activated through verified checkout');

        app(SubscriptionService::class)->createSubscription($this->tenant, $plan, 'monthly');
    }

    public function test_create_subscription_service_allows_paid_plans_only_when_explicitly_permitted(): void
    {
        $plan = $this->paidPlan();

        $subscription = app(SubscriptionService::class)
            ->createSubscription($this->tenant, $plan, 'monthly', null, true);

        $this->assertSame('active', $subscription->status);
        $this->assertSame($plan->id, $subscription->plan_id);
    }
}
