<?php

namespace App\Models;

use App\Health\Enums\IncidentSeverity;
use App\Health\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformIncident extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_incidents';

    protected $fillable = [
        'fingerprint',
        'title',
        'summary',
        'severity',
        'status',
        'source',
        'started_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'metadata',
    ];

    protected $casts = [
        'severity'       => IncidentSeverity::class,
        'status'         => IncidentStatus::class,
        'metadata'       => 'array',
        'started_at'     => 'datetime',
        'acknowledged_at'=> 'datetime',
        'resolved_at'    => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(PlatformIncidentEvent::class, 'incident_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Acknowledged->value]);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', IncidentStatus::Open->value);
    }

    public function scopeForFingerprint($query, string $fingerprint)
    {
        return $query->where('fingerprint', $fingerprint);
    }
}