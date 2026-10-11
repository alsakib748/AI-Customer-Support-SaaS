<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantProvisioningFailed;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantProvisioningFailed
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantProvisioningFailed $event): void
    {
        $tenant = $event->tenant;
        $suffix = $event->reason ? ' '.$event->reason : '';

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.provisioning_failed',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Error,
            title: 'Tenant provisioning failed',
            message: sprintf('Workspace "%s" could not be provisioned.%s', $tenant->name, $suffix),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
                'reason'      => $event->reason,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.provisioning_failed:'.$tenant->id,
        ));
    }
}
