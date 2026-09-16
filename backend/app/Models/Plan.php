<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'trial_days',
        'features',
        'limits',
        'is_active',
        'is_default',
        'is_public',
        'sort_order',
        'badge',
        'color',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'stripe_product_id',
        'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'formatted_price_monthly',
        'formatted_price_yearly',
        'yearly_savings',
        'yearly_savings_percentage',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getFormattedPriceMonthlyAttribute(): string
    {
        if ($this->price_monthly == 0) {
            return 'Free';
        }
        return $this->currency . ' ' . number_format($this->price_monthly, 2);
    }

    public function getFormattedPriceYearlyAttribute(): string
    {
        if ($this->price_yearly == 0) {
            return 'Free';
        }
        return $this->currency . ' ' . number_format($this->price_yearly, 2);
    }

    public function getYearlySavingsAttribute(): float
    {
        $monthlyTotal = $this->price_monthly * 12;
        return max(0, $monthlyTotal - $this->price_yearly);
    }

    public function getYearlySavingsPercentageAttribute(): float
    {
        $monthlyTotal = $this->price_monthly * 12;
        if ($monthlyTotal == 0) {
            return 0;
        }
        return round(($this->yearly_savings / $monthlyTotal) * 100, 1);
    }

    public function getLimit(string $key, $default = null)
    {
        return data_get($this->limits, $key, $default);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->features, $feature, false);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ============================================
    // METHODS
    // ============================================

    public function getPriceForCycle(string $cycle): float
    {
        return $cycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    public function isFree(): bool
    {
        return $this->price_monthly == 0 && $this->price_yearly == 0;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public static function getDefaultPlan(): ?self
    {
        return static::default()->active()->first()
            ?? static::active()->orderBy('price_monthly')->first();
    }

    public static function getFreePlan(): ?self
    {
        return static::active()->where('price_monthly', 0)->first();
    }
}