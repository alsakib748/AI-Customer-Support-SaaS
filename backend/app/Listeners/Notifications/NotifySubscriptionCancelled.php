<?php

namespace App\Listeners\Notifications;

use App\Events\Billing\SubscriptionCancelled;
use App\Models\Subscription;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifySubscriptionCancelled
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(SubscriptionCancelled $event): void
    {
        /** @var Subscription $subscription */
        $subscription = $event->subscription;
        $tenantName = $subscription->tenant?->name;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'billing.subscription_cancelled',
            category: NotificationCategory::Billing,
            severity: NotificationSeverity::Warning,
            title: 'Subscription cancelled',
            message: $tenantName
                ? sprintf('The subscription for "%s" was cancelled%s.', $tenantName, $event->immediately ? ' immediately' : '')
                : 'A platform subscription was cancelled.',
            actionUrl: '/admin/billing/subscriptions',
            entityType: 'subscription',
            entityId: (string) $subscription->id,
            meta: [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $subscription->tenant_id,
                'status'          => $subscription->status,
                'immediately'     => $event->immediately,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:billing.subscription_cancelled:'.$subscription->id,
        ));
    }
}
