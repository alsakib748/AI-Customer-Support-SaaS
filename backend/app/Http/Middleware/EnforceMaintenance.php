<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks tenant and widget traffic while platform maintenance mode is active.
 *
 * Design goals:
 *  - Platform administrators are ALWAYS allowed through (recovery path).
 *  - Fails open: if settings cannot be read (e.g. DB down) traffic is allowed,
 *    so a bad configuration can never permanently lock out the platform.
 *  - Registration can be optionally kept available during maintenance via the
 *    "registration" context parameter.
 */
class EnforceMaintenance
{
    public function handle(Request $request, Closure $next, ?string $context = null): Response
    {
        $service = app(SystemSettingsService::class);

        try {
            $enabled = $service->isMaintenanceEnabled();
        } catch (\Throwable $e) {
            return $next($request); // fail open
        }

        if (! $enabled) {
            return $next($request);
        }

        if ($context === 'registration'
            && $service->get('operations.allow_registration_during_maintenance', false)) {
            return $next($request);
        }

        $user = Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        $message = (string) $service->get(
            'operations.maintenance_message',
            'The platform is currently under maintenance.'
        );

        return response()->json([
            'success'     => false,
            'message'     => $message,
            'maintenance' => true,
            'code'        => 503,
        ], 503);
    }
}
