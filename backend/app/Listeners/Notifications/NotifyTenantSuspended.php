<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantSuspended;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantSuspended
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantSuspended $event): void
    {
        $tenant = $event->tenant;
        $suffix = $event->reason ? ': '.$event->reason : '.';

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.suspended',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Warning,
            title: 'Tenant suspended',
            message: sprintf('Workspace "%s" was suspended%s', $tenant->name, $suffix),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.suspended:'.$tenant->id,
        ));
    }
}
