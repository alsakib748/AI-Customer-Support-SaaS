<?php

namespace App\Listeners\Notifications;

use App\Events\Billing\SubscriptionExpired;
use App\Models\Subscription;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifySubscriptionExpired
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(SubscriptionExpired $event): void
    {
        /** @var Subscription $subscription */
        $subscription = $event->subscription;
        $tenantName = $subscription->tenant?->name;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'billing.subscription_expired',
            category: NotificationCategory::Billing,
            severity: NotificationSeverity::Warning,
            title: 'Subscription expired',
            message: $tenantName
                ? sprintf('The subscription for "%s" expired.', $tenantName)
                : 'A platform subscription expired.',
            actionUrl: '/admin/billing/subscriptions',
            entityType: 'subscription',
            entityId: (string) $subscription->id,
            meta: [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $subscription->tenant_id,
                'status'          => $subscription->status,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:billing.subscription_expired:'.$subscription->id,
        ));
    }
}
