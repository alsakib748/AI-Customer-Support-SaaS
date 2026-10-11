<?php

namespace App\Providers;

use App\Events\AI\AIResponseGenerated;
use App\Events\Billing\InvoicePaid;
use App\Events\Billing\PaymentCompleted;
use App\Events\Billing\PaymentFailed;
use App\Events\Billing\PaymentSucceeded;
use App\Events\Billing\SubscriptionCancelled;
use App\Events\Billing\SubscriptionCreated;
use App\Events\Billing\SubscriptionExpired;
use App\Events\Billing\SubscriptionRenewed;
use App\Events\Billing\SubscriptionUpgraded;
use App\Events\Billing\TrialEndingSoon;
use App\Events\Conversation\ConversationCreated;
use App\Events\Health\PlatformIncidentOpened;
use App\Events\Health\PlatformIncidentResolved;
use App\Events\KnowledgeBase\ArticleCreated;
use App\Events\Message\MessageCreated;
use App\Events\Tenant\TenantActivated;
use App\Events\Tenant\TenantArchived;
use App\Events\Tenant\TenantProvisioningCompleted;
use App\Events\Tenant\TenantProvisioningFailed;
use App\Events\Tenant\TenantRestored;
use App\Events\Tenant\TenantSuspended;
use App\Listeners\Ai\EnforceAiBillingLimit;
use App\Listeners\Billing\SendInvoicePaidNotification;
use App\Listeners\Billing\SendPaymentCompletedNotification;
use App\Listeners\Billing\SendPaymentFailedNotification;
use App\Listeners\Billing\SendPaymentSucceededNotification;
use App\Listeners\Billing\SendSubscriptionCancelledNotification;
use App\Listeners\Billing\SendSubscriptionCreatedNotification;
use App\Listeners\Billing\SendSubscriptionExpiredNotification;
use App\Listeners\Billing\SendSubscriptionRenewedNotification;
use App\Listeners\Billing\SendSubscriptionUpgradedNotification;
use App\Listeners\Billing\SendTrialEndingNotification;
use App\Listeners\Billing\SendWelcomeTrialNotification;
use App\Listeners\Billing\TrackUsageOnDomainEvent;
use App\Listeners\Message\ProcessMessageWithAI;
use App\Listeners\Notifications\NotifyPaymentFailed;
use App\Listeners\Notifications\NotifyPlatformIncidentOpened;
use App\Listeners\Notifications\NotifyPlatformIncidentResolved;
use App\Listeners\Notifications\NotifySubscriptionCancelled;
use App\Listeners\Notifications\NotifySubscriptionExpired;
use App\Listeners\Notifications\NotifyTenantActivated;
use App\Listeners\Notifications\NotifyTenantArchived;
use App\Listeners\Notifications\NotifyTenantProvisioningCompleted;
use App\Listeners\Notifications\NotifyTenantProvisioningFailed;
use App\Listeners\Notifications\NotifyTenantRestored;
use App\Listeners\Notifications\NotifyTenantSuspended;
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
        SubscriptionCreated::class => [
            SendSubscriptionCreatedNotification::class,
            SendWelcomeTrialNotification::class,
        ],
        SubscriptionUpgraded::class => [
            SendSubscriptionUpgradedNotification::class,
        ],
        SubscriptionCancelled::class => [
            SendSubscriptionCancelledNotification::class,
            NotifySubscriptionCancelled::class,
        ],
        SubscriptionRenewed::class => [
            SendSubscriptionRenewedNotification::class,
        ],
        SubscriptionExpired::class => [
            SendSubscriptionExpiredNotification::class,
            NotifySubscriptionExpired::class,
        ],
        TrialEndingSoon::class => [
            SendTrialEndingNotification::class,
        ],
        InvoicePaid::class => [
            SendInvoicePaidNotification::class,
        ],
        PaymentCompleted::class => [
            SendPaymentCompletedNotification::class,
        ],
        PaymentSucceeded::class => [
            SendPaymentSucceededNotification::class,
        ],
        PaymentFailed::class => [
            SendPaymentFailedNotification::class,
            NotifyPaymentFailed::class,
        ],

        // ===== PLATFORM NOTIFICATIONS =====
        PlatformIncidentOpened::class => [
            NotifyPlatformIncidentOpened::class,
        ],
        PlatformIncidentResolved::class => [
            NotifyPlatformIncidentResolved::class,
        ],
        TenantActivated::class => [
            NotifyTenantActivated::class,
        ],
        TenantSuspended::class => [
            NotifyTenantSuspended::class,
        ],
        TenantRestored::class => [
            NotifyTenantRestored::class,
        ],
        TenantProvisioningCompleted::class => [
            NotifyTenantProvisioningCompleted::class,
        ],
        TenantProvisioningFailed::class => [
            NotifyTenantProvisioningFailed::class,
        ],
        TenantArchived::class => [
            NotifyTenantArchived::class,
        ],

        // ===== MESSAGE → AI PIPELINE =====
        MessageCreated::class => [
            ProcessMessageWithAI::class,
            TrackUsageOnDomainEvent::class,
        ],

        // ===== DOMAIN → USAGE TRACKING =====
        ConversationCreated::class => [
            TrackUsageOnDomainEvent::class,
        ],
        ArticleCreated::class => [
            TrackUsageOnDomainEvent::class,
        ],

        // ===== AI BILLING ENFORCEMENT =====
        AIResponseGenerated::class => [
            EnforceAiBillingLimit::class,
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

    /**
     * The framework's base EventServiceProvider is always registered by
     * Application::configure() and already wires up the email verification
     * listener once. Because this provider extends that base class, leaving the
     * default behaviour would register SendEmailVerificationNotification twice
     * (Base provider + this subclass), causing duplicate verification emails.
     * The base provider owns this listener; this subclass must not repeat it.
     */
    protected function configureEmailVerification()
    {
        //
    }
}
