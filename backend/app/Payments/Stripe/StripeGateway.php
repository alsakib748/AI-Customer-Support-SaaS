<?php

namespace App\Payments\Stripe;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeGateway implements PaymentGateway
{
    protected StripeClient $client;

    public function __construct()
    {
        $secret = config('payment.providers.stripe.secret');

        if (!$secret) {
            throw new PaymentGatewayException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($secret);
    }

    public function identifier(): string
    {
        return 'stripe';
    }

    public function createCustomer(array $data): array
    {
        try {
            $customer = $this->client->customers->create([
                'email'    => $data['email'] ?? null,
                'name'     => $data['name'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            return [
                'provider_customer_id' => $customer->id,
                'raw'                  => $customer->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe createCustomer failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    public function createCheckout(array $data): array
    {
        try {
            $params = [
                'mode'                 => $data['mode'] ?? 'subscription',
                'customer'             => $data['provider_customer_id'],
                'success_url'          => $data['success_url'] ?? config('payment.redirects.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => $data['cancel_url']  ?? config('payment.redirects.cancel'),
                'allow_promotion_codes'=> $data['allow_promotion_codes'] ?? true,
                'metadata'             => $data['metadata'] ?? [],
            ];

            if (($data['mode'] ?? 'subscription') === 'subscription') {
                $params['line_items'] = [[
                    'price'    => $data['provider_price_id'],
                    'quantity' => 1,
                ]];
                $params['subscription_data'] = [
                    'metadata'        => $data['metadata'] ?? [],
                    'trial_period_days'=> $data['trial_days'] ?? null,
                ];
            } else {
                $params['line_items'] = [[
                    'price_data' => [
                        'currency'    => strtolower($data['currency'] ?? 'usd'),
                        'unit_amount' => (int) round(($data['amount'] ?? 0) * 100),
                        'product_data'=> ['name' => $data['description'] ?? 'Payment'],
                    ],
                    'quantity' => 1,
                ]];
            }

            $session = $this->client->checkout->sessions->create($params);

            return [
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'raw'          => $session->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe createCheckout failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe checkout failed: ' . $e->getMessage());
        }
    }

    public function createSubscription(array $data): array
    {
        try {
            $subscription = $this->client->subscriptions->create([
                'customer' => $data['provider_customer_id'],
                'items'    => [['price' => $data['provider_price_id']]],
                'metadata' => $data['metadata'] ?? [],
                'trial_period_days' => $data['trial_days'] ?? null,
            ]);

            return [
                'provider_subscription_id' => $subscription->id,
                'status'                   => $subscription->status,
                'raw'                      => $subscription->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe createSubscription failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe subscription failed: ' . $e->getMessage());
        }
    }

    public function changeSubscription(string $providerSubscriptionId, array $data): array
    {
        try {
            $subscription = $this->client->subscriptions->retrieve($providerSubscriptionId);

            // Change the price on the first item
            $updated = $this->client->subscriptions->update($providerSubscriptionId, [
                'items' => [[
                    'id'    => $subscription->items->data[0]->id,
                    'price' => $data['provider_price_id'],
                ]],
                'proration_behavior' => $data['proration_behavior'] ?? 'create_prorations',
                'metadata'           => $data['metadata'] ?? $subscription->metadata->toArray(),
            ]);

            return [
                'status' => $updated->status,
                'raw'    => $updated->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe changeSubscription failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe change failed: ' . $e->getMessage());
        }
    }

    public function cancelSubscription(string $providerSubscriptionId, bool $immediately = false): array
    {
        try {
            if ($immediately) {
                $subscription = $this->client->subscriptions->cancel($providerSubscriptionId);
            } else {
                $subscription = $this->client->subscriptions->update($providerSubscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }

            return [
                'status' => $subscription->status,
                'raw'    => $subscription->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe cancelSubscription failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe cancel failed: ' . $e->getMessage());
        }
    }

    public function resumeSubscription(string $providerSubscriptionId): array
    {
        try {
            $subscription = $this->client->subscriptions->update($providerSubscriptionId, [
                'cancel_at_period_end' => false,
            ]);

            return [
                'status' => $subscription->status,
                'raw'    => $subscription->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe resumeSubscription failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe resume failed: ' . $e->getMessage());
        }
    }

    public function getSubscription(string $providerSubscriptionId): array
    {
        try {
            $subscription = $this->client->subscriptions->retrieve($providerSubscriptionId);
            return $subscription->toArray();
        } catch (\Throwable $e) {
            Log::error('Stripe getSubscription failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe fetch failed: ' . $e->getMessage());
        }
    }

    public function createBillingPortal(string $providerCustomerId, array $options = []): array
    {
        try {
            $session = $this->client->billingPortal->sessions->create([
                'customer'   => $providerCustomerId,
                'return_url' => $options['return_url'] ?? config('payment.redirects.success'),
            ]);

            return ['url' => $session->url];
        } catch (\Throwable $e) {
            Log::error('Stripe createBillingPortal failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe portal failed: ' . $e->getMessage());
        }
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        $signature = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;
        if (!$signature) {
            return false;
        }

        try {
            Webhook::constructEvent(
                $payload,
                $signature,
                config('payment.providers.stripe.webhook_secret')
            );
            return true;
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe signature verification failed', ['error' => $e->getMessage()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Stripe webhook verify error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        $event = json_decode($payload, true) ?? [];
        $type  = $event['type'] ?? 'unknown';
        $data  = $event['data']['object'] ?? [];

        $normalized = $this->normalizeEvent($type, $data);

        return array_merge($normalized, [
            'provider'          => 'stripe',
            'provider_event_id' => $event['id'] ?? uniqid('evt_', true),
            'raw'               => $event,
        ]);
    }

    public function refund(string $providerPaymentId, ?float $amount = null): array
    {
        try {
            $params = ['payment_intent' => $providerPaymentId];
            if ($amount !== null) {
                $params['amount'] = (int) round($amount * 100);
            }

            $refund = $this->client->refunds->create($params);

            return [
                'refund_id' => $refund->id,
                'amount'    => $refund->amount / 100,
                'status'    => $refund->status,
                'raw'       => $refund->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe refund failed', ['error' => $e->getMessage()]);
            throw new PaymentGatewayException('Stripe refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Map a Stripe event to our normalized event shape.
     */
    protected function normalizeEvent(string $type, array $data): array
    {
        $map = [
            'invoice.paid'                          => 'payment.succeeded',
            'invoice.payment_failed'                => 'payment.failed',
            'invoice.payment_action_required'       => 'payment.pending',
            'checkout.session.completed'            => 'checkout.completed',
            'customer.subscription.created'         => 'subscription.activated',
            'customer.subscription.updated'         => 'subscription.updated',
            'customer.subscription.deleted'         => 'subscription.cancelled',
            'customer.subscription.trial_will_end'  => 'subscription.trial_ending',
            'charge.refunded'                       => 'payment.refunded',
        ];

        return [
            'type'                     => $map[$type] ?? 'unknown',
            'raw_type'                 => $type,
            'provider_customer_id'     => $data['customer']        ?? null,
            'provider_subscription_id' => $data['subscription']    ?? null,
            'provider_payment_id'      => $data['payment_intent']  ?? $data['charge'] ?? $data['id'] ?? null,
            'provider_invoice_id'      => $data['id']              ?? null,
            'amount'                   => isset($data['amount_paid'])
                ? $data['amount_paid'] / 100
                : (isset($data['amount']) ? $data['amount'] / 100 : null),
            'currency'                 => $data['currency'] ?? null,
            'metadata'                 => $data['metadata'] ?? [],
        ];
    }
}
