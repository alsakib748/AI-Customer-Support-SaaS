<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Billing\WebhookController;
use App\Jobs\Billing\ProcessBillingWebhookJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\Billing\BillingWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class WebhookHardeningTest extends TestCase
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

    protected function subscription(Plan $plan, array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_live_1',
            'provider_customer_id' => 'cus_live_1',
        ], $attributes));
    }

    protected function normalizedEvent(string $eventId, array $overrides = []): array
    {
        return array_merge([
            'type' => 'payment.succeeded',
            'raw_type' => 'invoice.paid',
            'provider' => 'stripe',
            'provider_event_id' => $eventId,
            'provider_customer_id' => 'cus_live_1',
            'provider_subscription_id' => 'sub_live_1',
            'provider_payment_id' => 'pi_live_1',
            'provider_invoice_id' => 'in_live_1',
            'amount' => 29.0,
            'currency' => 'USD',
            'metadata' => [],
            'raw' => ['id' => $eventId, 'type' => 'invoice.paid'],
        ], $overrides);
    }

    protected function webhookController(array $event): WebhookController
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('verifyWebhook')->andReturn(true);
        $gateway->shouldReceive('parseWebhook')->andReturn($event);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('has')->with('stripe')->andReturn(true);
        $manager->shouldReceive('driver')->with('stripe')->andReturn($gateway);

        app()->instance(PaymentGatewayManager::class, $manager);

        return app(WebhookController::class);
    }

    protected function stripeWebhookRequest(): Request
    {
        return Request::create(
            '/v1/webhooks/stripe',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'evt_test', 'type' => 'invoice.paid'])
        );
    }

    public function test_duplicate_delivery_dispatches_processing_only_once(): void
    {
        Queue::fake();

        $controller = $this->webhookController($this->normalizedEvent('evt_dup_1'));

        $first  = $controller->handle($this->stripeWebhookRequest(), 'stripe');
        $second = $controller->handle($this->stripeWebhookRequest(), 'stripe');

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());

        $this->assertSame(1, PaymentWebhookEvent::count());
        Queue::assertPushed(ProcessBillingWebhookJob::class, 1);
    }

    public function test_processed_event_is_not_reprocessed(): void
    {
        Queue::fake();

        $record = PaymentWebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_done_1',
            'event_type' => 'payment.succeeded',
            'status' => 'processed',
            'payload' => ['id' => 'evt_done_1'],
            'normalized_payload' => $this->normalizedEvent('evt_done_1'),
            'received_at' => now(),
        ]);

        $controller = $this->webhookController($this->normalizedEvent('evt_done_1'));
        $controller->handle($this->stripeWebhookRequest(), 'stripe');

        $this->assertSame(1, PaymentWebhookEvent::count());
        Queue::assertNotPushed(ProcessBillingWebhookJob::class);
        $this->assertSame('processed', $record->fresh()->status);
    }

    public function test_payment_succeeded_is_idempotent_for_invoice_and_payment(): void
    {
        $plan = $this->paidPlan();
        $this->subscription($plan);

        $service = app(BillingWebhookService::class);
        $event   = $this->normalizedEvent('evt_pay_1');

        $service->process($event);
        $service->process($event);

        $this->assertSame(1, Invoice::where('stripe_invoice_id', 'in_live_1')->count());
        $this->assertSame(1, Payment::where('provider_payment_id', 'pi_live_1')->count());
    }

    public function test_payment_succeeded_records_amount_mismatch(): void
    {
        Log::spy();

        $plan = $this->paidPlan();
        $this->subscription($plan);

        app(BillingWebhookService::class)->process(
            $this->normalizedEvent('evt_mismatch_1', ['amount' => 1.0])
        );

        $invoice = Invoice::where('stripe_invoice_id', 'in_live_1')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(1.0, (float) data_get($invoice->metadata, 'amount_mismatch.charged'));
        $this->assertSame(29.0, (float) data_get($invoice->metadata, 'amount_mismatch.expected'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => $message === 'Webhook amount does not match plan price')
            ->once();
    }

    public function test_failed_job_records_attempts_and_last_error(): void
    {
        $record = PaymentWebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_fail_1',
            'event_type' => 'payment.succeeded',
            'status' => 'processing',
            'payload' => ['id' => 'evt_fail_1'],
            'normalized_payload' => $this->normalizedEvent('evt_fail_1'),
            'received_at' => now(),
        ]);

        $processor = Mockery::mock(BillingWebhookService::class);
        $processor->shouldReceive('process')->once()->andThrow(new \RuntimeException('processor exploded'));

        try {
            (new ProcessBillingWebhookJob($record->id))->handle($processor);
        } catch (\Throwable $e) {
            // expected
        }

        $record->refresh();
        $this->assertSame('failed', $record->status);
        $this->assertSame(1, $record->attempts);
        $this->assertStringContainsString('processor exploded', $record->last_error);
    }

    public function test_event_receipt_timestamp_is_stored(): void
    {
        Queue::fake();

        $this->webhookController($this->normalizedEvent('evt_receipt_1'))
            ->handle($this->stripeWebhookRequest(), 'stripe');

        $record = PaymentWebhookEvent::where('provider_event_id', 'evt_receipt_1')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->received_at);
    }
}
