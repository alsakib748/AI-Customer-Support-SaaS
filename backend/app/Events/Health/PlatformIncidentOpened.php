<?php

namespace App\Events\Health;

use App\Health\Enums\IncidentSeverity;
use App\Models\PlatformIncident;
use App\Notifications\Contracts\PlatformNotificationEvent;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when System Health opens a new platform incident.
 */
class PlatformIncidentOpened implements PlatformNotificationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public PlatformIncident $incident)
    {
    }

    public function toPlatformNotification(): PlatformNotificationData
    {
        return new PlatformNotificationData(
            eventKey: 'health.incident.opened',
            category: NotificationCategory::SystemHealth,
            severity: self::severityFor($this->incident->severity),
            title: (string) ($this->incident->title ?: 'Platform incident opened'),
            message: (string) ($this->incident->summary ?: 'A platform service needs attention.'),
            actionUrl: '/admin/system-health/incidents/'.$this->incident->id,
            entityType: 'platform_incident',
            entityId: (string) $this->incident->id,
            meta: [
                'fingerprint'       => $this->incident->fingerprint,
                'incident_severity' => $this->incident->severity?->value,
                'source'            => $this->incident->source,
            ],
            occurredAt: $this->incident->started_at,
            dedupeKey: 'platform-notify:health.incident.opened:'.$this->incident->id,
        );
    }

    public static function severityFor(?IncidentSeverity $severity): NotificationSeverity
    {
        return match ($severity) {
            IncidentSeverity::Critical => NotificationSeverity::Critical,
            IncidentSeverity::Error    => NotificationSeverity::Error,
            IncidentSeverity::Warning  => NotificationSeverity::Warning,
            default                    => NotificationSeverity::Info,
        };
    }
}
