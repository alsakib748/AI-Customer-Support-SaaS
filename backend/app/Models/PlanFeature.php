<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_type',
        'value',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->feature_type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'limit' => $this->value === null ? null : (int) $this->value,
            default => $this->value,
        };
    }
}