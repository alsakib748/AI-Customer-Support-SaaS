<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantArchived;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantArchived
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantArchived $event): void
    {
        $tenant = $event->tenant;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.archived',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Warning,
            title: 'Tenant archived',
            message: sprintf('Workspace "%s" was archived.', $tenant->name),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.archived:'.$tenant->id,
        ));
    }
}
