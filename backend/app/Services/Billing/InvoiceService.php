<?php
// app/Services/Billing/InvoiceService.php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Get invoices for tenant
     */
    public function getInvoices(string $tenantId, array $filters = [])
    {
        $query = Invoice::forTenant($tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice(int $id): Invoice
    {
        return Invoice::with(['tenant', 'subscription', 'payments'])->findOrFail($id);
    }

    /**
     * Latest invoice for a subscription (used for idempotent creation).
     */
    public function getForSubscription(int $subscriptionId): ?Invoice
    {
        return Invoice::where('subscription_id', $subscriptionId)->latest('id')->first();
    }

    /**
     * Whether the subscription already has an invoice.
     */
    public function hasInvoiceForSubscription(int $subscriptionId): bool
    {
        return Invoice::where('subscription_id', $subscriptionId)->exists();
    }

    /**
     * Create subscription invoice
     */
    // public function createSubscriptionInvoice(Subscription $subscription): Invoice
    // {
    //     $plan = $subscription->plan;
    //     $amount = $plan->getPriceForCycle($subscription->billing_cycle);

    //     $lineItems = [
    //         [
    //             'description' => $plan->name . ' - ' . ucfirst($subscription->billing_cycle),
    //             'quantity' => 1,
    //             'unit_price' => $amount,
    //             'total' => $amount,
    //         ],
    //     ];

    //     // Apply coupon discounts
    //     $discountAmount = 0;
    //     foreach ($subscription->couponRedemptions as $redemption) {
    //         $discountAmount += $redemption->discount_amount;
    //     }

    //     $subtotal = $amount;
    //     $total = max(0, $subtotal - $discountAmount);

    //     $invoice = Invoice::create([
    //         'tenant_id' => $subscription->tenant_id,
    //         'subscription_id' => $subscription->id,
    //         'invoice_number' => Invoice::generateInvoiceNumber(),
    //         'subtotal' => $subtotal,
    //         'tax_amount' => 0,
    //         'discount_amount' => $discountAmount,
    //         'total' => $total,
    //         'currency' => $plan->currency,
    //         'status' => 'open',
    //         'due_at' => now()->addDays(7),
    //         'period_starts_at' => $subscription->starts_at,
    //         'period_ends_at' => $subscription->ends_at,
    //         'line_items' => $lineItems,
    //     ]);

    //     Log::info('Subscription invoice created', [
    //         'invoice_id' => $invoice->id,
    //         'subscription_id' => $subscription->id,
    //         'amount' => $total,
    //     ]);

    //     return $invoice;
    // }

    public function createSubscriptionInvoice(Subscription $subscription): Invoice
    {
        return DB::connection('central')->transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            $amount = $plan->getPriceForCycle($subscription->billing_cycle);

            $lineItems = [[
                'description' => $plan->name . ' — ' . ucfirst($subscription->billing_cycle),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'total'       => $amount,
            ]];

            // Apply coupon discounts if any
            $discount = 0;
            foreach ($subscription->couponRedemptions ?? [] as $redemption) {
                $discount += (float) $redemption->discount_amount;
            }

            $total = max(0, $amount - $discount);

            return Invoice::create([
                'tenant_id'              => $subscription->tenant_id,
                'subscription_id'        => $subscription->id,
                'invoice_number'         => $this->generateInvoiceNumber(),
                'subtotal'               => $amount,
                'tax_amount'             => 0,
                'discount_amount'        => $discount,
                'total'                  => $total,
                'currency'               => $plan->currency ?? 'USD',
                'status'                 => 'open',
                'due_at'                 => now()->addDays(config('billing.invoice.due_days', 7)),
                'period_starts_at'       => $subscription->current_period_starts_at ?? $subscription->starts_at,
                'period_ends_at'         => $subscription->current_period_ends_at   ?? $subscription->ends_at,
                'line_items'             => $lineItems,
            ]);
        });
    }



    /**
     * Create renewal invoice
     */
    public function createRenewalInvoice(Subscription $subscription): Invoice
    {
        return $this->createSubscriptionInvoice($subscription);
    }

    /**
     * Create proration invoice
     */
    public function createProrationInvoice(Subscription $subscription, float $amount): Invoice
    {
        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $amount,
            'currency' => $subscription->plan->currency ?? 'USD',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'line_items' => [
                [
                    'description' => 'Plan upgrade proration',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            ],
        ]);

        Log::info('Proration invoice created', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id,
            'amount' => $amount,
        ]);

        return $invoice;
    }

    /**
     * Mark invoice as paid
     */
    // public function markAsPaid(Invoice $invoice, string $paymentMethod = null, string $transactionId = null): Invoice
    // {
    //     $invoice->markAsPaid($paymentMethod, $transactionId);

    //     Log::info('Invoice marked as paid', [
    //         'invoice_id' => $invoice->id,
    //         'amount' => $invoice->total,
    //     ]);

    //     return $invoice->fresh();
    // }

    public function markAsPaid(Invoice $invoice, ?string $method = null, ?string $transactionId = null): Invoice
    {
        if ($invoice->status === 'paid') {
            return $invoice;
        }

        $invoice->update([
            'status'         => 'paid',
            'paid_at'        => now(),
            'payment_method' => $method,
            'transaction_id' => $transactionId,
        ]);

        Log::info('Invoice marked as paid', [
            'invoice_id' => $invoice->id,
            'amount'     => $invoice->total,
        ]);

        event(new \App\Events\Billing\InvoicePaid($invoice));

        return $invoice->fresh();
    }

    /**
     * Void invoice
     */
    // public function void(Invoice $invoice): Invoice
    // {
    //     $invoice->markAsVoid();

    //     Log::info('Invoice voided', [
    //         'invoice_id' => $invoice->id,
    //     ]);

    //     return $invoice->fresh();
    // }

    public function void(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'void']);
        return $invoice->fresh();
    }

    /**
     * Generate unique invoice number.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = config('billing.invoice.prefix', 'INV-');
        $period = date('Ym');

        // Include soft-deleted rows: invoice_number is unique, and ignoring
        // trashed rows would let a deleted number be reused and collide.
        $max = Invoice::withTrashed()
            ->where('invoice_number', 'LIKE', "{$prefix}{$period}%")
            ->get(['invoice_number'])
            ->reduce(function (?int $carry, $invoice) {
                if (preg_match('/-(\d{6})$/', (string) $invoice->invoice_number, $m)) {
                    return max($carry ?? 0, (int) $m[1]);
                }
                return $carry;
            });

        $next = str_pad(($max ?? 0) + 1, 6, '0', STR_PAD_LEFT);

        return "{$prefix}{$period}-{$next}";
    }

     /**
     * Get invoices for a tenant.
     */
    public function getForTenant(string $tenantId, array $filters = [])
    {
        return Invoice::where('tenant_id', $tenantId)
            ->when($filters['status']   ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['date_from']?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['date_to']  ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

        /**
     * Get invoice statistics
     */
    // public function getStatistics(string $tenantId): array
    // {
    //     $query = Invoice::forTenant($tenantId);

    //     return [
    //         'total' => $query->count(),
    //         'paid' => (clone $query)->where('status', 'paid')->count(),
    //         'open' => (clone $query)->where('status', 'open')->count(),
    //         'overdue' => (clone $query)->overdue()->count(),
    //         'total_amount' => (clone $query)->where('status', 'paid')->sum('total'),
    //         'outstanding_amount' => (clone $query)->where('status', 'open')->sum('total'),
    //     ];
    // }

    public function getStatistics(string $tenantId): array
    {
        $query = Invoice::where('tenant_id', $tenantId);

        return [
            'total'                => (clone $query)->count(),
            'paid'                 => (clone $query)->where('status', 'paid')->count(),
            'open'                 => (clone $query)->where('status', 'open')->count(),
            'overdue'              => (clone $query)->where('status', 'open')
                                        ->where('due_at', '<', now())->count(),
            'total_amount'         => (float) (clone $query)->where('status', 'paid')->sum('total'),
            'outstanding_amount'   => (float) (clone $query)->where('status', 'open')->sum('total'),
        ];
    }

}
