<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class CheckoutVerificationTest extends TestCase
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

    protected function paidPlan(): Plan
    {
        return $this->createDefaultPlan([
            'name' => 'Pro',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'currency' => 'USD',
            'trial_days' => 0,
        ]);
    }

    protected function pendingSubscription(Plan $plan): Subscription
    {
        return Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'provider' => 'stripe',
            'metadata' => ['checkout_session_id' => 'cs_test_1'],
        ]);
    }

    protected function controllerReturningSession(array $session): SubscriptionController
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('retrieveCheckoutSession')->andReturn($session);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('has')->andReturn(true);
        $manager->shouldReceive('driver')->andReturn($gateway);

        app()->instance(PaymentGatewayManager::class, $manager);

        return app(SubscriptionController::class);
    }

    protected function verifyRequest(): Request
    {
        return Request::create('/', 'POST', [
            'session_id' => 'cs_test_1',
            'provider' => 'stripe',
        ]);
    }

    public function test_verify_activates_paid_subscription_when_amount_matches(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'complete',
            'session_id' => 'cs_test_1',
            'amount_total' => 29.0,
            'currency' => 'USD',
            'provider_subscription_id' => 'sub_123',
            'provider_customer_id' => 'cus_1',
            'provider_payment_id' => 'pi_1',
            'metadata' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(200, $response->getStatusCode());

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('sub_123', $subscription->provider_subscription_id);
    }

    public function test_verify_rejects_an_amount_mismatch(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'complete',
            'amount_total' => 1.0,
            'currency' => 'USD',
            'provider_subscription_id' => 'sub_123',
            'provider_customer_id' => 'cus_1',
            'provider_payment_id' => 'pi_1',
            'metadata' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('amount_mismatch', $response->getData(true)['code']);
        $this->assertSame('pending', Subscription::where('tenant_id', $this->tenant->id)->first()->status);
    }

    public function test_verify_rejects_a_currency_mismatch(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'complete',
            'amount_total' => 29.0,
            'currency' => 'EUR',
            'provider_subscription_id' => 'sub_123',
            'provider_customer_id' => 'cus_1',
            'provider_payment_id' => 'pi_1',
            'metadata' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('currency_mismatch', $response->getData(true)['code']);
    }

    public function test_verify_rejects_a_session_from_another_tenant(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'complete',
            'amount_total' => 29.0,
            'currency' => 'USD',
            'provider_subscription_id' => 'sub_123',
            'provider_customer_id' => 'cus_1',
            'provider_payment_id' => 'pi_1',
            'metadata' => ['tenant_id' => (string) Str::uuid()],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_verify_reports_unpaid_for_an_incomplete_session(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'incomplete',
            'amount_total' => null,
            'currency' => 'USD',
            'metadata' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['data']['paid']);
        $this->assertSame('pending', Subscription::where('tenant_id', $this->tenant->id)->first()->status);
    }

    public function test_verify_accepts_paypal_completed_status(): void
    {
        $plan = $this->paidPlan();
        $this->pendingSubscription($plan);

        $controller = $this->controllerReturningSession([
            'status' => 'COMPLETED',
            'amount_total' => 29.0,
            'currency' => 'USD',
            'provider_subscription_id' => 'I-123',
            'provider_customer_id' => null,
            'provider_payment_id' => 'PAY-1',
            'metadata' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $controller->verify($this->verifyRequest(), app(\App\Services\Billing\BillingService::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('active', Subscription::where('tenant_id', $this->tenant->id)->first()->status);
    }
}
