<?php

namespace App\Providers;

use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            $manager = new PaymentGatewayManager();

            if (config('billing.providers.stripe.enabled', false)) {
                $manager->register('stripe', $app->make(StripeGateway::class));
            }

            if (config('billing.providers.paypal.enabled', false)) {
                $manager->register('paypal', $app->make(PayPalGateway::class));
            }

            return $manager;
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->gateway();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}