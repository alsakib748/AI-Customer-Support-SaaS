<?php

namespace App\Payments\PayPal;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $mode = config('payment.providers.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->clientId     = (string) config('payment.providers.paypal.client_id');
        $this->clientSecret = (string) config('payment.providers.paypal.client_secret');

        if (!$this->clientId || !$this->clientSecret) {
            throw new PaymentGatewayException('PayPal credentials are not configured.');
        }
    }

    public function identifier(): string
    {
        return 'paypal';
    }

    public function createCustomer(array $data): array
    {
        // PayPal has no standalone "customer" object for subscriptions.
        // We return the tenant reference; real identity is the subscription.
        return [
            'provider_customer_id' => $data['tenant_id'] ?? uniqid('pp_', true),
            'raw'                  => [],
        ];
    }

    public function createCheckout(array $data): array
    {
        // For subscriptions, use the Subscription API.
        if (($data['mode'] ?? 'subscription') === 'subscription') {
            return $this->createSubscription($data);
        }

        // One-time order via Orders API v2
        $order = $this->request('POST', '/v2/checkout/orders', [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $data['metadata']['invoice_id'] ?? uniqid('pu_', true),
                'amount'       => [
                    'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                    'value'         => number_format((float) $data['amount'], 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $data['success_url'] ?? config('payment.redirects.success'),
                'cancel_url' => $data['cancel_url']  ?? config('payment.redirects.cancel'),
                'brand_name' => config('app.name'),
            ],
        ]);

        $approve = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'checkout_url' => $approve,
            'session_id'   => $order['id'],
            'raw'          => $order,
        ];
    }

    public function createSubscription(array $data): array
    {
        $payload = [
            'plan_id'  => $data['provider_price_id'],
            'custom_id'=> (string) ($data['tenant_id'] ?? ''),
            'application_context' => [
                'brand_name'          => config('app.name'),
                'locale'              => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'SUBSCRIBE_NOW',
                'return_url'          => $data['success_url'] ?? config('payment.redirects.success'),
                'cancel_url'          => $data['cancel_url']  ?? config('payment.redirects.cancel'),
            ],
        ];

        if (!empty($data['metadata']['trial_days'])) {
            $payload['plan'] = [
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                ],
            ];
        }

        $result = $this->request('POST', '/v1/billing/subscriptions', $payload);

        $approve = collect($result['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'checkout_url'             => $approve,
            'provider_subscription_id' => $result['id'] ?? null,
            'status'                   => $result['status'] ?? null,
            'raw'                      => $result,
        ];
    }

    public function changeSubscription(string $providerSubscriptionId, array $data): array
    {
        // PayPal subscription plan changes are usually done by cancelling the
        // current subscription and creating a new one at the next billing cycle.
        // Simpler approach: use the "revise" endpoint.
        $result = $this->request('POST', "/v1/billing/subscriptions/{$providerSubscriptionId}/revise", [
            'plan_id' => $data['provider_price_id'],
        ]);

        return ['status' => $result['status'] ?? 'updated', 'raw' => $result];
    }

    public function cancelSubscription(string $providerSubscriptionId, bool $immediately = false): array
    {
        // PayPal doesn't have `cancel_at_period_end`. The subscription is
        // cancelled immediately at PayPal. We handle period-end locally.
        $this->request('POST', "/v1/billing/subscriptions/{$providerSubscriptionId}/cancel", [
            'reason' => $immediately ? 'Immediate cancellation' : 'Cancelled at period end',
        ]);

        return [
            'status' => 'CANCELLED',
            'raw'    => [],
        ];
    }

    public function resumeSubscription(string $providerSubscriptionId): array
    {
        // PayPal has no "resume" for cancelled subscriptions.
        // The tenant must subscribe again.
        throw new PaymentGatewayException('PayPal subscriptions cannot be resumed once cancelled.');
    }

    public function getSubscription(string $providerSubscriptionId): array
    {
        return $this->request('GET', "/v1/billing/subscriptions/{$providerSubscriptionId}");
    }

    public function createPlanPrice(array $data): array
    {
        throw new PaymentGatewayException(
            'Auto-provisioning is not supported for PayPal. Configure the plan in the PayPal dashboard.'
        );
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        // PayPal has no "checkout session"; the order capture is the closest equivalent.
        $order = $this->request('GET', "/v2/checkout/orders/{$sessionId}");

        return [
            'status'                   => $order['status'] ?? null,
            'session_id'               => $sessionId,
            'provider_subscription_id' => null,
            'provider_payment_id'      => $order['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
            'provider_customer_id'     => null,
            'metadata'                 => [],
            'raw'                      => $order,
        ];
    }

    public function createBillingPortal(string $providerCustomerId, array $options = []): array
    {
        // PayPal has no hosted billing portal. Redirect to PayPal's account page.
        return ['url' => 'https://www.paypal.com/myaccount/autopay/'];
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        try {
            $body = json_decode($payload, true);

            $verification = $this->request('POST', '/v1/notifications/verify-webhook-signature', [
                'auth_algo'         => $headers['paypal-auth-algo'][0]         ?? $headers['PAYPAL-AUTH-ALGO'][0]         ?? null,
                'cert_url'          => $headers['paypal-cert-url'][0]          ?? $headers['PAYPAL-CERT-URL'][0]          ?? null,
                'transmission_id'   => $headers['paypal-transmission-id'][0]   ?? $headers['PAYPAL-TRANSMISSION-ID'][0]   ?? null,
                'transmission_sig'  => $headers['paypal-transmission-sig'][0]  ?? $headers['PAYPAL-TRANSMISSION-SIG'][0]  ?? null,
                'transmission_time' => $headers['paypal-transmission-time'][0] ?? $headers['PAYPAL-TRANSMISSION-TIME'][0] ?? null,
                'webhook_id'        => config('payment.providers.paypal.webhook_id'),
                'webhook_event'     => $body,
            ]);

            return ($verification['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verify error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function parseWebhook(string $payload, array $headers): array
    {
        $event = json_decode($payload, true) ?? [];
        $type  = $event['event_type'] ?? 'unknown';
        $resource = $event['resource'] ?? [];

        $normalized = $this->normalizeEvent($type, $resource);

        return array_merge($normalized, [
            'provider'          => 'paypal',
            'provider_event_id' => $event['id'] ?? uniqid('wh_', true),
            'raw'               => $event,
        ]);
    }

    public function refund(string $providerPaymentId, ?float $amount = null): array
    {
        // For subscriptions, PayPal usually refunds via the capture ID.
        $payload = [];

        if ($amount !== null) {
            $payload = [
                'amount' => [
                    'value'         => number_format($amount, 2, '.', ''),
                    'currency_code' => config('payment.providers.paypal.currency', 'USD'),
                ],
            ];
        }

        $result = $this->request('POST', "/v2/payments/captures/{$providerPaymentId}/refund", $payload);

        return [
            'refund_id' => $result['id'] ?? null,
            'amount'    => isset($result['amount']['value']) ? (float) $result['amount']['value'] : null,
            'status'    => $result['status'] ?? 'PENDING',
            'raw'       => $result,
        ];
    }

    /**
     * Map a PayPal event to our normalized shape.
     */
    protected function normalizeEvent(string $type, array $resource): array
    {
        $map = [
            'BILLING.SUBSCRIPTION.CREATED'          => 'subscription.created',
            'BILLING.SUBSCRIPTION.ACTIVATED'        => 'subscription.activated',
            'BILLING.SUBSCRIPTION.UPDATED'          => 'subscription.updated',
            'BILLING.SUBSCRIPTION.CANCELLED'        => 'subscription.cancelled',
            'BILLING.SUBSCRIPTION.EXPIRED'          => 'subscription.expired',
            'BILLING.SUBSCRIPTION.SUSPENDED'        => 'subscription.suspended',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED'   => 'payment.failed',
            'PAYMENT.SALE.COMPLETED'                => 'payment.succeeded',
            'PAYMENT.SALE.REFUNDED'                 => 'payment.refunded',
            'PAYMENT.SALE.REVERSED'                 => 'payment.reversed',
        ];

        return [
            'type'                     => $map[$type] ?? 'unknown',
            'raw_type'                 => $type,
            'provider_customer_id'     => $resource['subscriber']['payer_id'] ?? null,
            'provider_subscription_id' => $resource['id']                     ?? $resource['billing_agreement_id'] ?? null,
            'provider_payment_id'      => $resource['id']                     ?? null,
            'provider_invoice_id'      => $resource['invoice_id']             ?? null,
            'amount'                   => isset($resource['amount']['total'])
                ? (float) $resource['amount']['total']
                : (isset($resource['amount']['value']) ? (float) $resource['amount']['value'] : null),
            'currency'                 => $resource['amount']['currency'] ?? $resource['amount']['currency_code'] ?? null,
            'metadata'                 => $resource['custom_id'] ?? [],
        ];
    }

    /**
     * Get an OAuth access token (cached).
     */
    protected function accessToken(): string
    {
        return Cache::remember('paypal_access_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                throw new PaymentGatewayException('PayPal OAuth failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Authenticated PayPal API request.
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $response = Http::withToken($this->accessToken())
            ->withHeaders([
                'Content-Type'    => 'application/json',
                'Prefer'          => 'return=representation',
                'PayPal-Request-Id' => (string) \Illuminate\Support\Str::uuid(),
            ])
            ->send($method, "{$this->baseUrl}{$endpoint}", ['json' => $data]);

        if (!$response->successful()) {
            Log::error('PayPal API error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            throw new PaymentGatewayException("PayPal API error ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
