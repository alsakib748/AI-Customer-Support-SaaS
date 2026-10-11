<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantActivated;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantActivated
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantActivated $event): void
    {
        $tenant = $event->tenant;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.activated',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Info,
            title: 'Tenant activated',
            message: sprintf('Workspace "%s" is now active on the platform.', $tenant->name),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.activated:'.$tenant->id,
        ));
    }
}
