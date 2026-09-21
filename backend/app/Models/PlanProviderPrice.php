<?php

namespace App\Models;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanProviderPrice extends Model
{
    protected $connection = 'central';
    protected $table      = 'plan_provider_prices';

    protected $fillable = [
        'plan_id',
        'provider',
        'billing_cycle',
        'currency',
        'provider_price_id',
        'amount',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
