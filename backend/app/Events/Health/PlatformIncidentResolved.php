<?php

namespace App\Events\Health;

use App\Models\PlatformIncident;
use App\Notifications\Contracts\PlatformNotificationEvent;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a platform incident is resolved (manually or automatically).
 */
class PlatformIncidentResolved implements PlatformNotificationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public PlatformIncident $incident)
    {
    }

    public function toPlatformNotification(): PlatformNotificationData
    {
        return new PlatformNotificationData(
            eventKey: 'health.incident.resolved',
            category: NotificationCategory::SystemHealth,
            severity: NotificationSeverity::Info,
            title: (string) ($this->incident->title ?: 'Platform incident resolved'),
            message: (string) ($this->incident->summary ?: 'The incident has recovered and is now resolved.'),
            actionUrl: '/admin/system-health/incidents/'.$this->incident->id,
            entityType: 'platform_incident',
            entityId: (string) $this->incident->id,
            meta: [
                'fingerprint' => $this->incident->fingerprint,
                'source'      => $this->incident->source,
            ],
            occurredAt: $this->incident->resolved_at,
            dedupeKey: 'platform-notify:health.incident.resolved:'.$this->incident->id,
        );
    }
}
