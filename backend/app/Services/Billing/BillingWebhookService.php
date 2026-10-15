<?php

namespace App\Services\Billing;

use App\Events\Billing\PaymentFailed;
use App\Events\Billing\PaymentSucceeded;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingWebhookService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected SubscriptionService $subscriptions,
        protected InvoiceService $invoices,
        protected PaymentService $payments,
        protected BillingDunningService $dunning,
    ) {}

    /**
     * Apply a normalized event to the database.
     */
    public function process(array $event): void
    {
        DB::connection('central')->transaction(function () use ($event) {
            match ($event['type']) {
                'payment.succeeded' => $this->paymentSucceeded($event),
                'payment.failed' => $this->paymentFailed($event),
                'payment.refunded' => $this->paymentRefunded($event),
                'checkout.completed' => $this->checkoutCompleted($event),
                'subscription.created' => $this->subscriptionCreated($event),
                'subscription.activated' => $this->subscriptionActivated($event),
                'subscription.updated' => $this->subscriptionUpdated($event),
                'subscription.cancelled' => $this->subscriptionCancelled($event),
                'subscription.expired' => $this->subscriptionExpired($event),
                'subscription.suspended' => $this->subscriptionSuspended($event),
                default => Log::info('Unhandled billing event', ['type' => $event['type']]),
            };
        });
    }

    protected function paymentSucceeded(array $event): void
    {
        $invoice = $this->findInvoice($event);
        $subscription = $invoice?->subscription ?? $this->findSubscription($event);

        // No local invoice yet (e.g. checkout during trial): create one from the
        // webhook payload so invoices and payments are kept in sync with Stripe.
        if (! $invoice && $subscription) {
            $invoice = $this->createInvoiceFromEvent($subscription, $event);
        }

        // The provider is the source of truth for what was charged, but flag any
        // divergence from our plan pricing so it can be investigated.
        $this->reconcileAmount($event, $invoice, $subscription);

        $payment = Payment::updateOrCreate(
            [
                'provider' => $event['provider'],
                'provider_payment_id' => $event['provider_payment_id'],
            ],
            [
                'tenant_id' => $invoice?->tenant_id ?? $subscription?->tenant_id,
                'invoice_id' => $invoice?->id,
                'subscription_id' => $invoice?->subscription_id ?? $subscription?->id,
                'provider_invoice_id' => $event['provider_invoice_id'],
                'provider_subscription_id' => $event['provider_subscription_id'],
                'amount' => $event['amount'],
                'currency' => $event['currency'] ?? 'USD',
                'status' => 'completed',
                'provider' => $event['provider'],
                'payment_method' => $event['provider'] === 'paypal' ? 'paypal' : 'card',
                'paid_at' => now(),
            ]
        );

        event(new PaymentSucceeded($payment));

        if ($invoice) {
            $this->invoices->markAsPaid($invoice, $event['provider'], $event['provider_payment_id']);
        }

        // Activate subscription if it's linked to this invoice
        if ($invoice?->subscription_id) {
            $this->subscriptions->activate($invoice->subscription_id);
        }

        // A successful payment recovers any billing suspension for the tenant.
        $recovered = $invoice?->subscription ?? $subscription;
        if ($recovered) {
            $this->dunning->reactivateForRecoveredPayment($recovered);
        }

        // Fallback: no local invoice exists for checkout flows. Activate the
        // pending subscription by its provider subscription id instead.
        if (! empty($event['provider_subscription_id'])) {
            $pending = Subscription::where('provider_subscription_id', $event['provider_subscription_id'])
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                $this->subscriptions->completePendingCheckout($pending, [
                    'provider_subscription_id' => $event['provider_subscription_id'],
                    'provider_customer_id' => $event['provider_customer_id'],
                    'source' => 'invoice.webhook',
                ]);
            }
        }
    }

    /**
     * Compare the charged amount with the plan price and log divergence.
     */
    protected function reconcileAmount(array $event, ?Invoice $invoice, ?Subscription $subscription): void
    {
        $plan = $subscription?->plan;

        if (! $plan || $plan->isFree()) {
            return;
        }

        $expected = $plan->getPriceForCycle($subscription->billing_cycle);
        $charged = $event['amount'] ?? null;

        if ($charged === null || $expected <= 0) {
            return;
        }

        if (abs((float) $charged - (float) $expected) > 0.01) {
            Log::warning('Webhook amount does not match plan price', [
                'provider' => $event['provider'] ?? null,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'expected' => $expected,
                'charged' => $charged,
            ]);

            if ($invoice) {
                $invoice->update([
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'amount_mismatch' => [
                            'expected' => $expected,
                            'charged' => $charged,
                            'at' => now()->toISOString(),
                        ],
                    ]),
                ]);
            }
        }
    }

    protected function createInvoiceFromEvent(Subscription $subscription, array $event): ?Invoice
    {
        $plan = $subscription->plan;
        $amount = $event['amount'] ?? (float) $plan?->getPriceForCycle($subscription->billing_cycle);
        $currency = strtoupper($event['currency'] ?? ($plan?->currency ?? 'USD'));
        $provider = $event['provider'];
        $usePaypal = $provider === 'paypal';

        $attributes = [
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_provider' => $provider,
            'payment_method' => 'card',
            $usePaypal ? 'paypal_invoice_id' : 'stripe_invoice_id' => $event['provider_invoice_id'] ?? null,
            'period_starts_at' => $this->periodFromMetadata($event, 'current_period_start'),
            'period_ends_at' => $this->periodFromMetadata($event, 'current_period_end'),
            'line_items' => [[
                'description' => ($plan?->name ?? 'Subscription').' — '.ucfirst($subscription->billing_cycle),
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ]],
            'metadata' => $event['metadata'] ?? [],
        ];

        // Keyed on the provider invoice id when present so a retried webhook
        // cannot create a second invoice.
        if (! empty($event['provider_invoice_id'])) {
            return Invoice::firstOrCreate(
                [$usePaypal ? 'paypal_invoice_id' : 'stripe_invoice_id' => $event['provider_invoice_id']],
                array_merge(['invoice_number' => $this->invoices->generateInvoiceNumber()], $attributes)
            );
        }

        return Invoice::create(array_merge(['invoice_number' => $this->invoices->generateInvoiceNumber()], $attributes));
    }

    protected function periodFromMetadata(array $event, string $key): ?Carbon
    {
        $value = $event['metadata'][$key] ?? null;
        if ($value === null) {
            return null;
        }

        return is_numeric($value)
            ? Carbon::createFromTimestamp((int) $value)
            : Carbon::parse($value);
    }

    protected function paymentFailed(array $event): void
    {
        $subscription = $this->findSubscription($event);

        // Key on the provider ids so a retried delivery updates the same row.
        $key = ! empty($event['provider_payment_id'])
            ? ['provider' => $event['provider'], 'provider_payment_id' => $event['provider_payment_id']]
            : ['provider' => $event['provider'], 'provider_invoice_id' => $event['provider_invoice_id']];

        $payment = Payment::updateOrCreate($key, [
            'tenant_id' => $subscription?->tenant_id,
            'subscription_id' => $subscription?->id,
            'provider' => $event['provider'],
            'provider_payment_id' => $event['provider_payment_id'],
            'provider_invoice_id' => $event['provider_invoice_id'],
            'amount' => $event['amount'] ?? 0,
            'currency' => $event['currency'] ?? 'USD',
            'status' => 'failed',
            'payment_method' => $event['provider'] === 'paypal' ? 'paypal' : 'card',
            'failure_reason' => $event['metadata']['failure_reason'] ?? 'Provider reported failure',
        ]);

        event(new PaymentFailed($payment, $event['metadata']['failure_reason'] ?? 'Provider reported failure'));

        if ($subscription) {
            $subscription->markAsPastDue();
        }
    }

    protected function paymentRefunded(array $event): void
    {
        $payment = Payment::where('provider', $event['provider'])
            ->where('provider_payment_id', $event['provider_payment_id'])
            ->first();

        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'refunded_amount' => $event['amount'] ?? $payment->amount,
                'refunded_at' => now(),
            ]);
        }
    }

    protected function checkoutCompleted(array $event): void
    {
        // The checkout session id is carried in provider_invoice_id (event object id).
        $sessionId = $event['provider_invoice_id'] ?? null;

        if (! $sessionId) {
            Log::warning('checkoutCompleted: missing session id', ['type' => $event['type'] ?? null]);

            return;
        }

        $subscription = Subscription::where('metadata->checkout_session_id', $sessionId)
            ->where('status', 'pending')
            ->first();

        if (! $subscription) {
            Log::warning('checkoutCompleted: no pending subscription for session', ['session_id' => $sessionId]);

            return;
        }

        $this->subscriptions->completePendingCheckout($subscription, [
            'provider_subscription_id' => $event['provider_subscription_id'],
            'provider_customer_id' => $event['provider_customer_id'],
            'provider_payment_id' => $event['provider_payment_id'] ?? null,
            'provider_invoice_id' => $event['provider_invoice_id'] ?? null,
            'source' => 'checkout.webhook',
        ]);

        // Recover any billing suspension now that checkout is complete.
        $this->dunning->reactivateForRecoveredPayment($subscription->fresh());
    }

    protected function subscriptionCreated(array $event): void
    {
        $this->upsertSubscription($event);
    }

    protected function subscriptionActivated(array $event): void
    {
        $this->upsertSubscription($event, ['status' => 'active']);
    }

    protected function subscriptionUpdated(array $event): void
    {
        $this->upsertSubscription($event);
    }

    protected function subscriptionCancelled(array $event): void
    {
        $this->upsertSubscription($event, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_at_period_end' => true,
        ]);
    }

    protected function subscriptionExpired(array $event): void
    {
        $this->upsertSubscription($event, ['status' => 'expired']);
    }

    protected function subscriptionSuspended(array $event): void
    {
        $this->upsertSubscription($event, ['status' => 'past_due']);
    }

    protected function upsertSubscription(array $event, array $override = []): void
    {
        $subscription = $this->findSubscription($event);

        if (! $subscription) {
            Log::warning('Webhook references unknown subscription', [
                'provider_subscription_id' => $event['provider_subscription_id'] ?? null,
                'provider' => $event['provider'] ?? null,
            ]);

            return;
        }

        $subscription->update(array_merge([
            'status' => $subscription->status,
            'provider_subscription_id' => $event['provider_subscription_id'] ?? $subscription->provider_subscription_id,
            'provider_customer_id' => $event['provider_customer_id'] ?? $subscription->provider_customer_id,
            'current_period_starts_at' => $event['metadata']['current_period_start'] ?? $subscription->current_period_starts_at,
            'current_period_ends_at' => $event['metadata']['current_period_end'] ?? $subscription->current_period_ends_at,
            'cancel_at_period_end' => $event['metadata']['cancel_at_period_end'] ?? $subscription->cancel_at_period_end,
        ], $override));
    }

    protected function findSubscription(array $event): ?Subscription
    {
        if (! empty($event['provider_subscription_id'])) {
            $sub = Subscription::where('provider_subscription_id', $event['provider_subscription_id'])->first();
            if ($sub) {
                return $sub;
            }
        }

        if (! empty($event['metadata']['tenant_id'])) {
            return Subscription::where('tenant_id', $event['metadata']['tenant_id'])
                ->where('provider', $event['provider'])
                ->latest()
                ->first();
        }

        return null;
    }

    protected function findInvoice(array $event): ?Invoice
    {
        if (! empty($event['provider_invoice_id'])) {
            $field = $event['provider'] === 'paypal' ? 'paypal_invoice_id' : 'stripe_invoice_id';
            $invoice = Invoice::where($field, $event['provider_invoice_id'])->first();
            if ($invoice) {
                return $invoice;
            }
        }

        if (! empty($event['metadata']['invoice_id'])) {
            return Invoice::find($event['metadata']['invoice_id']);
        }

        return null;
    }
}
