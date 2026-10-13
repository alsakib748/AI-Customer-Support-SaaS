<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\PlatformIncident;
use App\Models\SystemSetting;
use App\Observers\AuditLogObserver;
use App\Policies\PlatformIncidentPolicy;
use App\Policies\SystemSettingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(PlatformIncident::class, PlatformIncidentPolicy::class);

        AuditLog::observe(AuditLogObserver::class);

        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters for the unauthenticated auth endpoints. Without these
     * the register/login/forgot/reset routes can be brute-forced.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('password', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
