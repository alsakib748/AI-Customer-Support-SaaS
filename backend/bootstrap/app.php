<?php

use App\Console\Commands\Billing\BillingStatsCommand;
use App\Console\Commands\Health\Heartbeat;
use App\Console\Commands\Health\PruneHealthData;
use App\Console\Commands\Health\RunHealthChecks;
use App\Console\Commands\Notifications\PruneNotifications;
use App\Http\Middleware\AIRateLimit;
use App\Http\Middleware\BillingRateLimit;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnforceMaintenance;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\JWTAuthMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPermissionTeam;
use App\Http\Middleware\TenantAware;
use App\Http\Middleware\TrackUsage;
use App\Http\Middleware\VerifyTurnstile;
use App\Http\Middleware\WidgetRateLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => JWTAuthMiddleware::class,
            'tenant.aware' => TenantAware::class,
            'permission' => CheckPermission::class,
            'widget.rate.limit' => WidgetRateLimit::class,
            'ai.rate.limit' => AIRateLimit::class,
            'check.subscription' => CheckSubscription::class,
            'track.usage' => TrackUsage::class,
            'billing.rate.limit' => BillingRateLimit::class,
            'super.admin' => EnsureSuperAdmin::class,
            'maintenance.mode' => EnforceMaintenance::class,
            'role' => RoleMiddleware::class,
            'set.permission.team' => SetPermissionTeam::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'turnstile' => VerifyTurnstile::class,
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withCommands([
        BillingStatsCommand::class,
        RunHealthChecks::class,
        Heartbeat::class,
        PruneHealthData::class,
        PruneNotifications::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
