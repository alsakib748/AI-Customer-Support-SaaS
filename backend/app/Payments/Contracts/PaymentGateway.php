<?php

namespace App\Payments\Contracts;

interface PaymentGateway
{
    /**
     * Get the provider identifier (e.g., 'stripe', 'paypal').
     */
    public function identifier(): string;

    /**
     * Create or retrieve a customer on the provider.
     */
    public function createCustomer(array $data): array;

    /**
     * Create a checkout session for a subscription or one-time payment.
     *
     * @return array{checkout_url: string, session_id: string, provider_subscription_id?: string}
     */
    public function createCheckout(array $data): array;

    /**
     * Create a provider-side subscription directly (server-to-server).
     */
    public function createSubscription(array $data): array;

    /**
     * Change an existing subscription (upgrade/downgrade).
     */
    public function changeSubscription(string $providerSubscriptionId, array $data): array;

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(string $providerSubscriptionId, bool $immediately = false): array;

    /**
     * Resume a subscription that was set to cancel at period end.
     */
    public function resumeSubscription(string $providerSubscriptionId): array;

    /**
     * Fetch a subscription's current state from the provider.
     */
    public function getSubscription(string $providerSubscriptionId): array;

    /**
     * Create a provider-side price/plan for a billing cycle (auto-provisioning).
     */
    public function createPlanPrice(array $data): array;

    /**
     * Fetch a checkout session's current state from the provider.
     */
    public function retrieveCheckoutSession(string $sessionId): array;

    /**
     * Create a billing portal URL for the customer.
     */
    public function createBillingPortal(string $providerCustomerId, array $options = []): array;

    /**
     * Verify an incoming webhook's signature.
     */
    public function verifyWebhook(string $payload, array $headers): bool;

    /**
     * Parse a webhook payload into a normalized billing event.
     *
     * @return array{type: string, provider: string, provider_event_id: string, provider_customer_id: ?string, provider_subscription_id: ?string, provider_payment_id: ?string, provider_invoice_id: ?string, amount: ?float, currency: ?string, raw: array, metadata: array}
     */
    public function parseWebhook(string $payload, array $headers): array;

    /**
     * Issue a refund.
     */
    public function refund(string $providerPaymentId, ?float $amount = null): array;
}
