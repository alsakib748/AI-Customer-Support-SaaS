<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
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
        ],
        \App\Events\Billing\SubscriptionRenewed::class => [
            \App\Listeners\Billing\SendSubscriptionRenewedNotification::class,
        ],
        \App\Events\Billing\SubscriptionExpired::class => [
            \App\Listeners\Billing\SendSubscriptionExpiredNotification::class,
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
        ],

        // ===== DOMAIN → USAGE TRACKING =====
        \App\Events\Messages\MessageCreated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Conversations\ConversationStarted::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Customers\CustomerCreated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Team\AgentCreated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Widgets\WidgetCreated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\KnowledgeBase\ArticleCreated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Ai\AiResponseGenerated::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],
        \App\Events\Ai\AiResponseGenerated::class => [
    \App\Listeners\Ai\EnforceAiBillingLimit::class,
],
        \App\Events\Ai\AiTokensConsumed::class => [\App\Listeners\Billing\TrackUsageOnDomainEvent::class],

        // ===== AI LIMIT ENFORCEMENT =====
        \App\Events\Ai\AiRequestCreated::class => [
            \App\Listeners\Ai\EnforceAiBillingLimit::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
