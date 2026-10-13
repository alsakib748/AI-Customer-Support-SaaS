<?php

namespace Tests\Feature\Ops;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithBillingDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Phase 6 WS3 regression: a Stripe checkout session is not proof of payment.
 * A completed but unpaid session must never activate a subscription.
 */
class CheckoutVerifyPaymentStatusTest extends TestCase
{
    use InteractsWithBillingDomain;
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();
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

    public function test_unpaid_stripe_session_does_not_activate_a_subscription(): void
    {
        [$tenant] = $this->bootBillingWorkspace(withSubscription: false);

        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with('cs_test_unpaid')
            ->andReturn([
                'metadata' => ['tenant_id' => $tenant->id],
                'status' => 'complete',
                'payment_status' => 'unpaid',
                'amount_total' => 2900,
                'currency' => 'usd',
            ]);

        $this->mock(PaymentGatewayManager::class, function ($manager) use ($gateway) {
            $manager->shouldReceive('has')->with('stripe')->andReturn(true);
            $manager->shouldReceive('driver')->with('stripe')->andReturn($gateway);
        });

        $this->postJson('/api/v1/subscription/checkout/verify', [
            'session_id' => 'cs_test_unpaid',
            'provider' => 'stripe',
        ])->assertOk()
            ->assertJsonPath('data.paid', false)
            ->assertJsonPath('data.payment_status', 'unpaid');

        $this->assertSame(
            0,
            DB::connection('central')->table('subscriptions')->where('tenant_id', $tenant->id)->count(),
            'an unpaid session must not create or activate a subscription',
        );
    }
}
