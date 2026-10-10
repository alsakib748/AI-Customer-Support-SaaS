<?php

namespace App\Models;

use App\Health\Enums\IncidentSeverity;
use App\Health\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformIncidentEvent extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_incident_events';

    public const EVENT_OPENED       = 'opened';
    public const EVENT_ACKNOWLEDGED = 'acknowledged';
    public const EVENT_RESOLVED     = 'resolved';
    public const EVENT_REOPENED     = 'reopened';
    public const EVENT_UPDATED      = 'updated';

    protected $fillable = [
        'incident_id',
        'event',
        'severity',
        'summary',
        'actor_id',
        'meta',
    ];

    protected $casts = [
        'severity' => IncidentSeverity::class,
        'meta'     => 'array',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PlatformIncident::class, 'incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function effectiveStatus(): IncidentStatus
    {
        return match ($this->event) {
            self::EVENT_ACKNOWLEDGED => IncidentStatus::Acknowledged,
            self::EVENT_RESOLVED     => IncidentStatus::Resolved,
            default                  => IncidentStatus::Open,
        };
    }
}