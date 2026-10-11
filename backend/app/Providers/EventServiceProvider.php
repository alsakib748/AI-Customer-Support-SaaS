<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The application's event → listener mappings.
     *
     * This is the SINGLE source of truth for event wiring. Automatic event
     * discovery is disabled in bootstrap/app.php (->withEvents(discover: false))
     * so every listener is registered exactly once — including object-typed
     * listeners such as TrackUsageOnDomainEvent and EnforceAiBillingLimit which
     * discovery could never resolve on its own.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ===== BILLING LIFECYCLE =====
        \App\Events\Billing\SubscriptionCreated::class => [
            \App\Listeners\Billing\SendSubscriptionCreatedNotification::class,
            \App\Listeners\Billing\SendWelcomeTrialNotification::class,
        ],
        \App\Events\Billing\SubscriptionUpgraded::class => [
            \App\Listeners\Billing\SendSubscriptionUpgradedNotification::class,
        ],
        \App\Events\Billing\SubscriptionCancelled::class => [
            \App\Listeners\Billing\SendSubscriptionCancelledNotification::class,
            \App\Listeners\Notifications\NotifySubscriptionCancelled::class,
        ],
        \App\Events\Billing\SubscriptionRenewed::class => [
            \App\Listeners\Billing\SendSubscriptionRenewedNotification::class,
        ],
        \App\Events\Billing\SubscriptionExpired::class => [
            \App\Listeners\Billing\SendSubscriptionExpiredNotification::class,
            \App\Listeners\Notifications\NotifySubscriptionExpired::class,
        ],
        \App\Events\Billing\TrialEndingSoon::class => [
            \App\Listeners\Billing\SendTrialEndingNotification::class,
        ],
        \App\Events\Billing\InvoicePaid::class => [
            \App\Listeners\Billing\SendInvoicePaidNotification::class,
        ],
        \App\Events\Billing\PaymentCompleted::class => [
            \App\Listeners\Billing\SendPaymentCompletedNotification::class,
        ],
        \App\Events\Billing\PaymentSucceeded::class => [
            \App\Listeners\Billing\SendPaymentSucceededNotification::class,
        ],
        \App\Events\Billing\PaymentFailed::class => [
            \App\Listeners\Billing\SendPaymentFailedNotification::class,
            \App\Listeners\Notifications\NotifyPaymentFailed::class,
        ],

        // ===== PLATFORM NOTIFICATIONS =====
        \App\Events\Health\PlatformIncidentOpened::class => [
            \App\Listeners\Notifications\NotifyPlatformIncidentOpened::class,
        ],
        \App\Events\Health\PlatformIncidentResolved::class => [
            \App\Listeners\Notifications\NotifyPlatformIncidentResolved::class,
        ],
        \App\Events\Tenant\TenantActivated::class => [
            \App\Listeners\Notifications\NotifyTenantActivated::class,
        ],
        \App\Events\Tenant\TenantSuspended::class => [
            \App\Listeners\Notifications\NotifyTenantSuspended::class,
        ],
        \App\Events\Tenant\TenantRestored::class => [
            \App\Listeners\Notifications\NotifyTenantRestored::class,
        ],
        \App\Events\Tenant\TenantProvisioningCompleted::class => [
            \App\Listeners\Notifications\NotifyTenantProvisioningCompleted::class,
        ],
        \App\Events\Tenant\TenantProvisioningFailed::class => [
            \App\Listeners\Notifications\NotifyTenantProvisioningFailed::class,
        ],
        \App\Events\Tenant\TenantArchived::class => [
            \App\Listeners\Notifications\NotifyTenantArchived::class,
        ],

        // ===== MESSAGE → AI PIPELINE =====
        \App\Events\Message\MessageCreated::class => [
            \App\Listeners\Message\ProcessMessageWithAI::class,
            \App\Listeners\Billing\TrackUsageOnDomainEvent::class,
        ],

        // ===== DOMAIN → USAGE TRACKING =====
        \App\Events\Conversation\ConversationCreated::class => [
            \App\Listeners\Billing\TrackUsageOnDomainEvent::class,
        ],
        \App\Events\KnowledgeBase\ArticleCreated::class => [
            \App\Listeners\Billing\TrackUsageOnDomainEvent::class,
        ],

        // ===== AI BILLING ENFORCEMENT =====
        \App\Events\AI\AIResponseGenerated::class => [
            \App\Listeners\Ai\EnforceAiBillingLimit::class,
        ],
    ];

    /**
     * Event discovery is disabled in bootstrap/app.php; this provider owns all
     * registrations explicitly.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
