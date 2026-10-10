<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single platform-wide configuration value.
 *
 * Values are allow-listed by config/system_settings.php; this model is a thin
 * persistence layer and should not be queried directly by application code.
 * Use App\Services\SystemSettings\SystemSettingsService instead.
 */
class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $connection = 'central';

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'is_public',
        'updated_by',
    ];

    protected $casts = [
        'value'      => 'json',
        'is_public'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
