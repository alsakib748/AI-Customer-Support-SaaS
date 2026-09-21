<?php

namespace App\Providers;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, fn () => new PaymentGatewayManager());

        $this->app->bind(PaymentGateway::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->driver();
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
