<?php
// app/Services/Billing/PaymentService.php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected InvoiceService $invoiceService;
    protected PaymentGatewayManager $gateways;

    public function __construct(InvoiceService $invoiceService,PaymentGatewayManager $gateways,)
    {
        $this->invoiceService = $invoiceService;
        $this->gateways = $gateways;
    }

    /**
     * List payments for a tenant.
     */
    public function getForTenant(string $tenantId, array $filters = [])
    {
        return Payment::where('tenant_id', $tenantId)
            ->when($filters['status']   ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['provider'] ?? null, fn ($q, $p) => $q->where('provider', $p))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get payments for tenant
     */
    public function getPayments(string $tenantId, array $filters = [])
    {
        $query = Payment::forTenant($tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create a payment record
     */
    public function createPayment(
        string $tenantId,
        float $amount,
        string $provider,
        string $paymentMethod,
        ?int $invoiceId = null,
        ?int $subscriptionId = null
    ): Payment {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'subscription_id' => $subscriptionId,
            'payment_id' => 'pay_' . Str::random(24),
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'provider' => $provider,
            'payment_method' => $paymentMethod,
        ]);

        Log::info('Payment created', [
            'payment_id' => $payment->id,
            'tenant_id' => $tenantId,
            'amount' => $amount,
        ]);

        return $payment;
    }

    /**
     * Process payment
     */
    public function processPayment(Payment $payment): Payment
    {
        try {
            $payment->update(['status' => 'processing']);

            // Process with provider
            // This would integrate with Stripe/PayPal

            // Mark as completed
            $payment->markAsCompleted();

            // Update invoice if exists
            if ($payment->invoice) {
                $this->invoiceService->markAsPaid(
                    $payment->invoice,
                    $payment->payment_method,
                    $payment->payment_id
                );
            }

            Log::info('Payment processed', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);

            return $payment->fresh();

        } catch (\Exception $e) {
            $payment->markAsFailed($e->getMessage());

            Log::error('Payment failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refund payment
     */
    // public function refund(Payment $payment, float $amount = null, string $reason = null): Payment
    // {
    //     if (!$payment->can_refund) {
    //         throw new \Exception('Payment cannot be refunded.');
    //     }

    //     $payment->refund($amount, $reason);

    //     Log::info('Payment refunded', [
    //         'payment_id' => $payment->id,
    //         'amount' => $amount ?? $payment->refundable_amount,
    //     ]);

    //     return $payment->fresh();
    // }

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        $refundable = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);

        if ($amount !== null && $amount > $refundable) {
            throw new \InvalidArgumentException('Refund exceeds refundable amount.');
        }

        $amount = $amount ?? $refundable;

        if (!$payment->provider_payment_id) {
            throw new \RuntimeException('Cannot refund a payment without a provider reference.');
        }

        $gateway = $this->gateways->driver($payment->provider);
        $result = $gateway->refund($payment->provider_payment_id, $amount);

        return DB::connection('central')->transaction(function () use ($payment, $amount, $reason, $result) {
            $newRefunded = (float) ($payment->refunded_amount ?? 0) + $amount;
            $isFull = $newRefunded >= (float) $payment->amount;

            $payment->update([
                'refunded_amount' => $newRefunded,
                'refunded_at'     => now(),
                'refund_reason'   => $reason,
                'status'          => $isFull ? 'refunded' : 'partially_refunded',
                'metadata'        => array_merge($payment->metadata ?? [], [
                    'last_refund_id'     => $result['refund_id'] ?? null,
                    'last_refund_status' => $result['status']    ?? null,
                ]),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Get payment statistics
     */
    // public function getStatistics(string $tenantId): array
    // {
    //     $query = Payment::forTenant($tenantId);

    //     return [
    //         'total' => $query->count(),
    //         'completed' => (clone $query)->where('status', 'completed')->count(),
    //         'failed' => (clone $query)->where('status', 'failed')->count(),
    //         'refunded' => (clone $query)->whereIn('status', ['refunded', 'partially_refunded'])->count(),
    //         'total_revenue' => (clone $query)->where('status', 'completed')->sum('amount'),
    //         'total_refunded' => (clone $query)->sum('refunded_amount'),
    //         'by_provider' => (clone $query)
    //             ->where('status', 'completed')
    //             ->selectRaw('provider, count(*) as count, sum(amount) as total')
    //             ->groupBy('provider')
    //             ->get()
    //             ->toArray(),
    //     ];
    // }

    public function getStatistics(string $tenantId): array
    {
        $query = Payment::where('tenant_id', $tenantId);

        return [
            'total'           => (clone $query)->count(),
            'completed'       => (clone $query)->where('status', 'completed')->count(),
            'failed'          => (clone $query)->where('status', 'failed')->count(),
            'refunded'        => (clone $query)->whereIn('status', ['refunded', 'partially_refunded'])->count(),
            'total_revenue'   => (float) (clone $query)->where('status', 'completed')->sum('amount'),
            'total_refunded'  => (float) (clone $query)->sum('refunded_amount'),
            'by_provider'     => (clone $query)
                ->where('status', 'completed')
                ->selectRaw('provider, count(*) as count, sum(amount) as total')
                ->groupBy('provider')
                ->get()
                ->toArray(),
        ];
    }
}
