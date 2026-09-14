<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsExport extends Model
{
    protected $table = 'analytics_exports';
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'format',
        'filters',
        'file_path',
        'file_name',
        'row_count',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['is_ready', 'download_url'];

    public function getIsReadyAttribute(): bool
    {
        return $this->status === 'completed' && !empty($this->file_path);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->is_ready
            ? route('api.v1.analytics.exports.download', ['export' => $this->id])
            : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}