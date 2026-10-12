<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

/**
 * Fixtures for the billing / payment HTTP surface.
 *
 * Billing models live on the central connection, so these helpers seed the
 * central schema directly while a tenant context is established for the
 * authenticated request.
 */
trait InteractsWithBillingDomain
{
    /**
     * Boot a tenant workspace and authenticate an owner.
     *
     * When $withSubscription is false the tenant has no subscription at all.
     * Billing routes are reachable without one (CheckSubscription whitelists
     * them), but the controller still refuses to create a second one.
     *
     * @return array{0: Tenant, 1: User, 2: Subscription|null}
     */
    protected function bootBillingWorkspace(bool $withSubscription = true, string $role = 'owner'): array
    {
        $tenant = $this->newTenant();
        $user = $this->newTenantUser($tenant, $role);
        $subscription = $withSubscription ? $this->newSubscription($tenant) : null;

        $this->establishTenantContext($tenant, $user);
        $this->withoutMiddleware($this->tenantHttpMiddlewareToBypass());

        return [$tenant, $user, $subscription];
    }

    protected function seedInvoice(Tenant $tenant, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-'.date('Ym').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'subtotal' => 29.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 29.00,
            'currency' => 'USD',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'line_items' => [
                ['description' => 'Pro — Monthly', 'quantity' => 1, 'unit_price' => 29.00, 'total' => 29.00],
            ],
        ], $overrides));
    }

    protected function seedPayment(Tenant $tenant, array $overrides = []): Payment
    {
        return Payment::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'status' => 'completed',
            'provider' => 'stripe',
            'payment_method' => 'card',
        ], $overrides));
    }
}
