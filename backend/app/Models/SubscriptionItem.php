<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'subscription_items';

    protected $fillable = [
        'subscription_id',
        'type',
        'name',
        'slug',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'stripe_item_id',
        'stripe_price_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $appends = [
        'formatted_unit_price',
        'formatted_total_price',
        'type_label',
        'type_color',
        'is_addon',
        'is_seat',
        'is_usage',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getFormattedUnitPriceAttribute(): string
    {
        $currency = $this->subscription?->plan?->currency ?? 'USD';
        return $currency . ' ' . number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        $currency = $this->subscription?->plan?->currency ?? 'USD';
        return $currency . ' ' . number_format($this->total_price, 2);
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'addon' => 'Add-on',
            'seat' => 'Additional Seat',
            'usage' => 'Usage-Based',
            'one_time' => 'One-Time',
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getTypeColorAttribute(): string
    {
        $colors = [
            'addon' => 'info',
            'seat' => 'primary',
            'usage' => 'warning',
            'one_time' => 'secondary',
        ];
        return $colors[$this->type] ?? 'secondary';
    }

    public function getIsAddonAttribute(): bool
    {
        return $this->type === 'addon';
    }

    public function getIsSeatAttribute(): bool
    {
        return $this->type === 'seat';
    }

    public function getIsUsageAttribute(): bool
    {
        return $this->type === 'usage';
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAddons($query)
    {
        return $query->where('type', 'addon');
    }

    public function scopeSeats($query)
    {
        return $query->where('type', 'seat');
    }

    public function scopeUsage($query)
    {
        return $query->where('type', 'usage');
    }

    public function scopeForSubscription($query, int $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Calculate and update the total price
     */
    public function calculateTotal(): self
    {
        $this->total_price = $this->quantity * $this->unit_price;
        $this->save();

        return $this;
    }

    /**
     * Update quantity and recalculate
     */
    public function updateQuantity(int $quantity): self
    {
        $this->update(['quantity' => $quantity]);
        $this->calculateTotal();

        return $this;
    }

    /**
     * Get the subscription's current plan currency
     */
    public function getCurrency(): string
    {
        return $this->subscription?->plan?->currency ?? 'USD';
    }

    /**
     * Check if this item is currently active
     */
    public function isActive(): bool
    {
        return $this->subscription?->is_active ?? false;
    }
}