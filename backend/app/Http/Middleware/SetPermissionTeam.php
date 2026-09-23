<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $tenant = $request->attributes->get('current_tenant')
            ?? app()->bound('current_tenant') ? app('current_tenant') : null;

        // Super admins always operate in platform scope: their role holds no
        // tenant_id, so scoping to a tenant would strip platform permissions.
        if ($user && $user->isSuperAdmin()) {
            setPermissionsTeamId(null);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');

            return $next($request);
        }

        // If Super Admin with no tenant context, leave team unset (platform scope)
        if (!$tenant) {
            // Ensure platform scope
            setPermissionsTeamId(null);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $next($request);
        }

        // Verify membership before setting team
        if ($user && !$user->isSuperAdmin()) {
            $belongs = $user->tenants()
                ->where('tenants.id', $tenant->id)
                ->exists();

            if (!$belongs) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this workspace.',
                ], 403);
            }
        }

        // Set the Spatie team context
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Earlier middleware (e.g. TenantAware) may have cached the user's
        // roles/permissions relations while the team scope was still unset.
        // Reset them so subsequent checks resolve against the current tenant.
        if ($user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return $next($request);
    }
}