<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'type',
        'quantity',
        'unit',
        'period_date',
        'period_type',
        'cost',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'period_date' => 'date',
        'cost' => 'decimal:6',
        'metadata' => 'array',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('period_date', [$startDate, $endDate]);
    }

    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }

    // ============================================
    // METHODS
    // ============================================

    public static function record(string $tenantId, string $type, int $quantity, ?int $subscriptionId = null): self
    {
        $record = static::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'type' => $type,
                'period_date' => now()->toDateString(),
                'period_type' => 'daily',
            ],
            [
                'subscription_id' => $subscriptionId,
                'quantity' => 0,
                'unit' => 'count',
            ]
        );

        $record->increment('quantity', $quantity);

        return $record;
    }
}