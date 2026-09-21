<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'invoice_id',
        'subscription_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'provider',
        'payment_method',
        'last_four',
        'card_brand',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'paypal_transaction_id',
        'refunded_amount',
        'refunded_at',
        'refund_reason',
        'metadata',
        'failure_reason',
        'paid_at',
        'provider_payment_id',
'provider_invoice_id',
'provider_subscription_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'formatted_amount',
        'is_successful',
        'is_refunded',
        'can_refund',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'partially_refunded' => 'Partially Refunded',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'refunded' => 'secondary',
            'partially_refunded' => 'warning',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsRefundedAttribute(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }

    public function getCanRefundAttribute(): bool
    {
        return $this->is_successful
            && $this->refunded_amount < $this->amount;
    }

    public function getRefundableAmountAttribute(): float
    {
        return max(0, $this->amount - $this->refunded_amount);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    // ============================================
    // METHODS
    // ============================================

    public function markAsCompleted(): self
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(string $reason = null): self
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);

        return $this;
    }

    public function refund(float $amount = null, string $reason = null): self
    {
        $amount = $amount ?? $this->refundable_amount;

        if ($amount > $this->refundable_amount) {
            throw new \Exception('Refund amount exceeds refundable amount.');
        }

        $newRefundedAmount = $this->refunded_amount + $amount;
        $isFullRefund = $newRefundedAmount >= $this->amount;

        $this->update([
            'refunded_amount' => $newRefundedAmount,
            'refunded_at' => now(),
            'refund_reason' => $reason,
            'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
        ]);

        return $this;
    }
}
