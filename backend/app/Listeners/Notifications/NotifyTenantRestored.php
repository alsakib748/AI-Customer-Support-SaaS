<?php

namespace App\Listeners\Notifications;

use App\Events\Tenant\TenantRestored;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyTenantRestored
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(TenantRestored $event): void
    {
        $tenant = $event->tenant;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'tenant.restored',
            category: NotificationCategory::Tenant,
            severity: NotificationSeverity::Info,
            title: 'Tenant reactivated',
            message: sprintf('Workspace "%s" was reactivated.', $tenant->name),
            actionUrl: '/admin/tenants/'.$tenant->id,
            entityType: 'tenant',
            entityId: (string) $tenant->id,
            meta: [
                'tenant_name' => $tenant->name,
                'status'      => $tenant->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:tenant.restored:'.$tenant->id,
        ));
    }
}
