<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingWebhookService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected SubscriptionService $subscriptions,
        protected InvoiceService $invoices,
        protected PaymentService $payments,
    ) {}

    /**
     * Apply a normalized event to the database.
     */
    public function process(array $event): void
    {
        DB::connection('central')->transaction(function () use ($event) {
            match ($event['type']) {
                'payment.succeeded'       => $this->paymentSucceeded($event),
                'payment.failed'          => $this->paymentFailed($event),
                'payment.refunded'        => $this->paymentRefunded($event),
                'checkout.completed'      => $this->checkoutCompleted($event),
                'subscription.created'    => $this->subscriptionCreated($event),
                'subscription.activated'  => $this->subscriptionActivated($event),
                'subscription.updated'    => $this->subscriptionUpdated($event),
                'subscription.cancelled'  => $this->subscriptionCancelled($event),
                'subscription.expired'    => $this->subscriptionExpired($event),
                'subscription.suspended'  => $this->subscriptionSuspended($event),
                default                   => Log::info('Unhandled billing event', ['type' => $event['type']]),
            };
        });
    }

    protected function paymentSucceeded(array $event): void
    {
        $invoice = $this->findInvoice($event);

        $payment = Payment::updateOrCreate(
            [
                'provider'          => $event['provider'],
                'provider_payment_id'=> $event['provider_payment_id'],
            ],
            [
                'tenant_id'               => $invoice?->tenant_id,
                'invoice_id'              => $invoice?->id,
                'subscription_id'         => $invoice?->subscription_id,
                'provider_invoice_id'     => $event['provider_invoice_id'],
                'provider_subscription_id'=> $event['provider_subscription_id'],
                'amount'                  => $event['amount'],
                'currency'                => $event['currency'] ?? 'USD',
                'status'                  => 'completed',
                'provider'                => $event['provider'],
                'paid_at'                 => now(),
            ]
        );

        event(new \App\Events\Billing\PaymentSucceeded($payment));

        if ($invoice) {
            $this->invoices->markAsPaid($invoice, $event['provider'], $event['provider_payment_id']);
        }

        // Activate subscription if it's linked to this invoice
        if ($invoice?->subscription_id) {
            $this->subscriptions->activate($invoice->subscription_id);
        }
    }

    protected function paymentFailed(array $event): void
    {
        $subscription = $this->findSubscription($event);

         $reason = $event['metadata']['failure_reason'] ?? 'Provider reported failure';

        $payment = Payment::create([
            'tenant_id'               => $subscription?->tenant_id,
            'subscription_id'         => $subscription?->id,
            'provider'                => $event['provider'],
            'provider_payment_id'     => $event['provider_payment_id'],
            'provider_invoice_id'     => $event['provider_invoice_id'],
            'amount'                  => $event['amount'] ?? 0,
            'currency'                => $event['currency'] ?? 'USD',
            'status'                  => 'failed',
            'failure_reason'          => $event['metadata']['failure_reason'] ?? 'Provider reported failure',
        ]);

        event(new \App\Events\Billing\PaymentFailed($payment, $event['metadata']['failure_reason'] ?? 'Provider reported failure'));

        if ($subscription) {
            $subscription->update(['status' => 'past_due']);
        }
    }

    protected function paymentRefunded(array $event): void
    {
        $payment = Payment::where('provider', $event['provider'])
            ->where('provider_payment_id', $event['provider_payment_id'])
            ->first();

        if ($payment) {
            $payment->update([
                'status'          => 'refunded',
                'refunded_amount' => $event['amount'] ?? $payment->amount,
                'refunded_at'     => now(),
            ]);
        }
    }

    protected function checkoutCompleted(array $event): void
    {
        // Nothing to do here — activation happens on invoice.paid / subscription.activated
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
            'status'          => 'cancelled',
            'cancelled_at'    => now(),
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

        if (!$subscription) {
            Log::warning('Webhook references unknown subscription', [
                'provider_subscription_id' => $event['provider_subscription_id'] ?? null,
                'provider'                 => $event['provider'] ?? null,
            ]);
            return;
        }

        $subscription->update(array_merge([
            'status'              => $subscription->status,
            'current_period_starts_at' => $event['metadata']['current_period_start'] ?? $subscription->current_period_starts_at,
            'current_period_ends_at'   => $event['metadata']['current_period_end']   ?? $subscription->current_period_ends_at,
            'cancel_at_period_end'=> $event['metadata']['cancel_at_period_end'] ?? $subscription->cancel_at_period_end,
        ], $override));
    }

    protected function findSubscription(array $event): ?Subscription
    {
        if (!empty($event['provider_subscription_id'])) {
            $sub = Subscription::where('provider_subscription_id', $event['provider_subscription_id'])->first();
            if ($sub) return $sub;
        }

        if (!empty($event['metadata']['tenant_id'])) {
            return Subscription::where('tenant_id', $event['metadata']['tenant_id'])
                ->where('provider', $event['provider'])
                ->latest()
                ->first();
        }

        return null;
    }

    protected function findInvoice(array $event): ?Invoice
    {
        if (!empty($event['provider_invoice_id'])) {
            return Invoice::where('provider_invoice_id', $event['provider_invoice_id'])->first();
        }

        if (!empty($event['metadata']['invoice_id'])) {
            return Invoice::find($event['metadata']['invoice_id']);
        }

        return null;
    }
}
