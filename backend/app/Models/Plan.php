<?php
namespace App\Models;

use App\Models\PlanProviderPrice;
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
        'features'      => 'array',
        'limits'        => 'array',
        'metadata'      => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly'  => 'decimal:2',
        'trial_days'    => 'integer',
        'is_active'     => 'boolean',
        'is_default'    => 'boolean',
        'is_public'     => 'boolean',
        'sort_order'    => 'integer',
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
        if ((float) $this->price_monthly === 0.0) {
            return 'Free';
        }
        return $this->currency . ' ' . number_format((float) $this->price_monthly, 2);
    }

    public function getFormattedPriceYearlyAttribute(): string
    {
        if ((float) $this->price_yearly === 0.0) {
            return 'Free';
        }
        return $this->currency . ' ' . number_format((float) $this->price_yearly, 2);
    }

    public function getYearlySavingsAttribute(): float
    {
        $monthlyTotal = (float) $this->price_monthly * 12;
        return max(0, $monthlyTotal - (float) $this->price_yearly);
    }

    public function getYearlySavingsPercentageAttribute(): float
    {
        $monthlyTotal = (float) $this->price_monthly * 12;
        if ($monthlyTotal === 0.0) {
            return 0.0;
        }
        return round(($this->yearly_savings / $monthlyTotal) * 100, 1);
    }

    public function getLimit(string $key, mixed $default = null): mixed
    {
        $feature = $this->relationLoaded('planFeatures')
            ? $this->planFeatures->firstWhere('feature_key', $key)
            : $this->planFeatures()->where('feature_key', $key)->first();

        if ($feature) {
            return $feature->typed_value;
        }

        // Fallback to JSON limits (legacy compatibility)
        return data_get($this->limits, $key, $default);
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
        return $cycle === 'yearly'
            ? (float) $this->price_yearly
            : (float) $this->price_monthly;
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
        return static::default()->active()->first() ?? static::active()->orderBy('price_monthly')->first();
    }

    public static function getFreePlan(): ?self
    {
        return static::active()->where('price_monthly', 0)->first();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function hasFeature(string $feature): bool
    {
        $row = $this->relationLoaded('planFeatures')
            ? $this->planFeatures->firstWhere('feature_key', $feature)
            : $this->planFeatures()->where('feature_key', $feature)->first();

        if ($row) {
            return (bool) $row->typed_value && $row->is_enabled;
        }

        return (bool) data_get($this->features, $feature, false);
    }

    public function providerPrices(): HasMany
    {
        return $this->hasMany(PlanProviderPrice::class);
    }

    public function providerPrice(string $provider, string $cycle, string $currency = 'USD'): ?PlanProviderPrice
    {
        return $this->providerPrices()
            ->where('provider', $provider)
            ->where('billing_cycle', $cycle)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();
    }

}
