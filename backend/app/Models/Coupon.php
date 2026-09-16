<?php

namespace App\Models;

use App\Models\CouponRedemption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'type',
        'value',
        'currency',
        'duration',
        'duration_months',
        'max_redemptions',
        'times_redeemed',
        'max_redemptions_per_tenant',
        'applicable_plans',
        'minimum_amount',
        'starts_at',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'duration_months' => 'integer',
        'max_redemptions' => 'integer',
        'times_redeemed' => 'integer',
        'max_redemptions_per_tenant' => 'integer',
        'applicable_plans' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'is_valid',
        'is_expired',
        'is_exhausted',
        'formatted_value',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getIsValidAttribute(): bool
    {
        return $this->is_active && !$this->is_expired && !$this->is_exhausted;
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    public function getIsExhaustedAttribute(): bool
    {
        if (!$this->max_redemptions) {
            return false;
        }
        return $this->times_redeemed >= $this->max_redemptions;
    }

    public function getFormattedValueAttribute(): string
    {
        if ($this->type === 'percentage') {
            return $this->value . '%';
        }
        return $this->currency . ' ' . number_format($this->value, 2);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_redemptions')
                    ->orWhereRaw('times_redeemed < max_redemptions');
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // ============================================
    // METHODS
    // ============================================

    public function isValid(): bool
    {
        return $this->is_valid;
    }

    public function isApplicableToPlan(Plan $plan): bool
    {
        if (empty($this->applicable_plans)) {
            return true;
        }
        return in_array($plan->id, $this->applicable_plans)
            || in_array($plan->slug, $this->applicable_plans);
    }

    public function meetsMinimumAmount(float $amount): bool
    {
        if (!$this->minimum_amount) {
            return true;
        }
        return $amount >= $this->minimum_amount;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            return round(($amount * $this->value) / 100, 2);
        }
        return min($this->value, $amount);
    }

    public function canBeRedeemedBy(string $tenantId): bool
    {
        $redemptions = $this->redemptions()
            ->where('tenant_id', $tenantId)
            ->count();

        return $redemptions < $this->max_redemptions_per_tenant;
    }

    public function redeem(string $tenantId, ?int $subscriptionId = null, float $discountAmount = 0): CouponRedemption
    {
        $redemption = $this->redemptions()->create([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'discount_amount' => $discountAmount,
            'currency' => $this->currency,
        ]);

        $this->increment('times_redeemed');

        return $redemption;
    }
}