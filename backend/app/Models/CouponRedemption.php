<?php

namespace App\Models;

use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'coupon_id',
        'tenant_id',
        'subscription_id',
        'discount_amount',
        'currency',
        'metadata',
        'redeemed_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'metadata' => 'array',
        'redeemed_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}