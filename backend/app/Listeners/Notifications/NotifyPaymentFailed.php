<?php

namespace App\Listeners\Notifications;

use App\Events\Billing\PaymentFailed;
use App\Models\Payment;
use App\Notifications\Data\PlatformNotificationData;
use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use App\Notifications\Services\PlatformNotificationService;

class NotifyPaymentFailed
{
    public function __construct(protected PlatformNotificationService $notifications)
    {
    }

    public function handle(PaymentFailed $event): void
    {
        /** @var Payment $payment */
        $payment = $event->payment;

        $this->notifications->send(new PlatformNotificationData(
            eventKey: 'billing.payment_failed',
            category: NotificationCategory::Billing,
            severity: NotificationSeverity::Error,
            title: 'Payment processing failed',
            message: sprintf(
                'A payment of %s %s failed. %s',
                $payment->currency ?? '',
                $payment->amount ?? '',
                $event->reason ?: 'Please review the billing activity.',
            ),
            actionUrl: '/admin/billing/payments',
            entityType: 'payment',
            entityId: (string) $payment->id,
            meta: [
                'payment_id'   => $payment->id,
                'tenant_id'    => $payment->tenant_id,
                'reason'       => $event->reason,
                'amount'       => $payment->amount,
                'currency'     => $payment->currency,
            ],
            occurredAt: now(),
            dedupeKey: 'platform-notify:billing.payment_failed:'.$payment->id,
        ));
    }
}
