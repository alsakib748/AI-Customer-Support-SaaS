<?php

namespace App\Models;

use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SubscriptionItem;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_starts_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'paused_at',
        'resumed_at',
        'auto_renew',
        'next_billing_at',
        'last_billing_at',
        'ai_used',
        'ai_limit',
        'agents_used',
        'agents_limit',
        'documents_used',
        'documents_limit',
        'storage_used',
        'storage_limit',
        'conversations_used',
        'conversations_limit',
        'stripe_subscription_id',
        'stripe_customer_id',
        'paypal_subscription_id',
        'metadata',
    ];

    protected $casts = [
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'last_billing_at' => 'datetime',
        'auto_renew' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'is_active',
        'is_trialing',
        'is_cancelled',
        'is_expired',
        'is_on_trial',
        'days_remaining',
        'trial_days_remaining',
        'usage_percentage',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    // Helper methods to get items by type
    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class)->where('type', 'addon');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class)->where('type', 'seat');
    }

    public function usageItems(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class)->where('type', 'usage');
    }

    // Get total additional charges
    public function getAdditionalChargesAttribute(): float
    {
        return $this->items()->sum('total_price');
    }

    // Get grand total (plan + items)
    public function getGrandTotalAttribute(): float
    {
        $planPrice = $this->plan?->getPriceForCycle($this->billing_cycle) ?? 0;
        return $planPrice + $this->additional_charges;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'trialing' => 'Trial',
            'active' => 'Active',
            'past_due' => 'Past Due',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            'paused' => 'Paused',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'trialing' => 'info',
            'active' => 'success',
            'past_due' => 'warning',
            'cancelled' => 'danger',
            'expired' => 'danger',
            'paused' => 'secondary',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['trialing', 'active']);
    }

    public function getIsTrialingAttribute(): bool
    {
        return $this->status === 'trialing';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'expired'
            || ($this->ends_at && $this->ends_at->isPast() && $this->status !== 'active');
    }

    public function getIsOnTrialAttribute(): bool
    {
        return $this->is_trialing
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->ends_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function getTrialDaysRemainingAttribute(): ?int
    {
        if (!$this->trial_ends_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    public function getUsagePercentageAttribute(): array
    {
        return [
            'ai' => $this->ai_limit > 0 ? round(($this->ai_used / $this->ai_limit) * 100, 1) : 0,
            'agents' => $this->agents_limit > 0 ? round(($this->agents_used / $this->agents_limit) * 100, 1) : 0,
            'documents' => $this->documents_limit > 0 ? round(($this->documents_used / $this->documents_limit) * 100, 1) : 0,
            'storage' => $this->storage_limit > 0 ? round(($this->storage_used / $this->storage_limit) * 100, 1) : 0,
            'conversations' => $this->conversations_limit > 0 ? round(($this->conversations_used / $this->conversations_limit) * 100, 1) : 0,
        ];
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trialing', 'active']);
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->where('ends_at', '<=', now()->addDays($days))
            ->where('ends_at', '>', now());
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ============================================
    // METHODS
    // ============================================

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isTrialing(): bool
    {
        return $this->is_trialing;
    }

    public function isCancelled(): bool
    {
        return $this->is_cancelled;
    }

    public function isExpired(): bool
    {
        return $this->is_expired;
    }

    public function onTrial(): bool
    {
        return $this->is_on_trial;
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function canUpgrade(): bool
    {
        return $this->is_active;
    }

    public function canDowngrade(): bool
    {
        return $this->is_active;
    }

    public function canCancel(): bool
    {
        return !$this->is_cancelled && $this->status !== 'expired';
    }

    public function cancel(bool $immediately = false): self
    {
        $this->update([
            'status' => $immediately ? 'cancelled' : $this->status,
            'cancelled_at' => now(),
            'auto_renew' => false,
            'ends_at' => $immediately ? now() : $this->ends_at,
        ]);

        return $this;
    }

    public function resume(): self
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'auto_renew' => true,
            'resumed_at' => now(),
        ]);

        return $this;
    }

    public function pause(): self
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'auto_renew' => false,
        ]);

        return $this;
    }

    public function expire(): self
    {
        $this->update([
            'status' => 'expired',
            'ends_at' => now(),
        ]);

        return $this;
    }

    public function activate(): self
    {
        $this->update([
            'status' => 'active',
            'starts_at' => $this->starts_at ?? now(),
        ]);

        return $this;
    }

    public function markAsPastDue(): self
    {
        $this->update(['status' => 'past_due']);
        return $this;
    }

    public function renew(): self
    {
        $this->update([
            'status' => 'active',
            'last_billing_at' => now(),
            'next_billing_at' => $this->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
            'ends_at' => $this->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
        ]);

        return $this;
    }

    public function hasReachedLimit(string $type): bool
    {
        $usedField = "{$type}_used";
        $limitField = "{$type}_limit";

        if (!isset($this->$limitField) || $this->$limitField <= 0) {
            return false; // Unlimited
        }

        return $this->$usedField >= $this->$limitField;
    }

    public function incrementUsage(string $type, int $amount = 1): self
    {
        $field = "{$type}_used";

        if (isset($this->$field)) {
            $this->increment($field, $amount);
        }

        return $this;
    }

    public function decrementUsage(string $type, int $amount = 1): self
    {
        $field = "{$type}_used";

        if (isset($this->$field)) {
            $this->decrement($field, $amount);
        }

        return $this;
    }

    public function resetUsage(): self
    {
        $this->update([
            'ai_used' => 0,
            'conversations_used' => 0,
        ]);

        return $this;
    }

    public function switchPlan(Plan $plan, string $cycle = null): self
    {
        $cycle = $cycle ?? $this->billing_cycle;

        $this->update([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'ai_limit' => $plan->getLimit('ai_messages', 1000),
            'agents_limit' => $plan->getLimit('agents', 5),
            'documents_limit' => $plan->getLimit('documents', 100),
            'storage_limit' => $plan->getLimit('storage_bytes', 1073741824),
            'conversations_limit' => $plan->getLimit('conversations', 500),
            'next_billing_at' => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        return $this;
    }
}