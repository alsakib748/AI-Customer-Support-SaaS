<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\PlatformIncident;
use App\Models\SystemSetting;
use App\Observers\AuditLogObserver;
use App\Policies\PlatformIncidentPolicy;
use App\Policies\SystemSettingPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
