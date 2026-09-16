<?php
// app/Services/Billing/StripeService.php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Plan;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription as StripeSubscription;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Webhook;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create or get Stripe customer
     */
    public function getOrCreateCustomer(Subscription $subscription): string
    {
        if ($subscription->stripe_customer_id) {
            return $subscription->stripe_customer_id;
        }

        $tenant = $subscription->tenant;
        $owner = $tenant->getOwner();

        $customer = Customer::create([
            'email' => $owner?->email,
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
            ],
        ]);

        $subscription->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Create checkout session for subscription
     */
    public function createCheckoutSession(
        Subscription $subscription,
        Plan $plan,
        string $cycle = 'monthly'
    ): CheckoutSession {
        $customerId = $this->getOrCreateCustomer($subscription);

        $priceId = $cycle === 'yearly'
            ? $plan->stripe_price_id_yearly
            : $plan->stripe_price_id_monthly;

        if (!$priceId) {
            throw new \Exception('Stripe price ID not configured for this plan.');
        }

        $session = CheckoutSession::create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                ],
            ],
            'success_url' => config('app.frontend_url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/billing/cancel',
            'allow_promotion_codes' => true,
        ]);

        Log::info('Stripe checkout session created', [
            'session_id' => $session->id,
            'subscription_id' => $subscription->id,
        ]);

        return $session;
    }

    /**
     * Create payment intent for one-time payment
     */
    public function createPaymentIntent(Invoice $invoice): PaymentIntent
    {
        $subscription = $invoice->subscription;
        $customerId = $this->getOrCreateCustomer($subscription);

        $intent = PaymentIntent::create([
            'amount' => (int) ($invoice->total * 100), // Convert to cents
            'currency' => strtolower($invoice->currency),
            'customer' => $customerId,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id' => $invoice->tenant_id,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        Log::info('Stripe payment intent created', [
            'intent_id' => $intent->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
        ]);

        return $intent;
    }

    /**
     * Cancel Stripe subscription
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void
    {
        if (!$subscription->stripe_subscription_id) {
            return;
        }

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            if ($immediately) {
                $stripeSubscription->cancel();
            } else {
                $stripeSubscription->cancel_at_period_end = true;
                $stripeSubscription->save();
            }

            Log::info('Stripe subscription cancelled', [
                'stripe_id' => $subscription->stripe_subscription_id,
                'immediately' => $immediately,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel Stripe subscription', [
                'stripe_id' => $subscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            Webhook::constructEvent($payload, $signature, $secret);
            return true;
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle webhook event
     */
    public function handleWebhookEvent(array $event): void
    {
        $type = $event['type'] ?? null;

        Log::info('Processing Stripe webhook', ['type' => $type]);

        match ($type) {
            'invoice.paid' => $this->handleInvoicePaid($event['data']['object']),
            'invoice.payment_failed' => $this->handlePaymentFailed($event['data']['object']),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event['data']['object']),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event['data']['object']),
            'checkout.session.completed' => $this->handleCheckoutCompleted($event['data']['object']),
            default => Log::info('Unhandled Stripe webhook event', ['type' => $type]),
        };
    }

    protected function handleInvoicePaid(array $invoice): void
    {
        // Implementation
    }

    protected function handlePaymentFailed(array $invoice): void
    {
        // Implementation
    }

    protected function handleSubscriptionDeleted(array $subscription): void
    {
        // Implementation
    }

    protected function handleSubscriptionUpdated(array $subscription): void
    {
        // Implementation
    }

    protected function handleCheckoutCompleted(array $session): void
    {
        // Implementation
    }
}