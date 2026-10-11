<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantProvisioningCompleted;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantProvisioningCompleted
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantProvisioningCompleted $event): void
    {
        $tenant = $event->tenant;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.registered',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Info,
            title: 'New tenant registered',
            message: sprintf('Workspace "%s" was created and provisioned on the platform.', $tenant->name),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.registered:'.$tenant->id,
        ));
    }
}
