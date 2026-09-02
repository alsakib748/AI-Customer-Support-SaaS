<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class TenantAware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Check if user is Super Admin
        $user = auth()->user();

        if ($user && $user->hasRole('super-admin')) {
            // Super Admin doesn't need tenant context
            // They can access platform-level routes
            Log::info('Super Admin accessing route without tenant', [
                'user_id' => $user->id,
                'path' => $request->path(),
            ]);

            // Set a special flag for Super Admin
            $request->attributes->set('is_super_admin', true);

            return $next($request);
        }

        $tenant = null;

        // 1. From Stancl's tenant identification
        if (tenancy()->tenant) {
            $tenant = tenancy()->tenant;
        }

        // 2. From header
        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-Id')
                ?? $request->header('X-Tenant-ID')
                ?? $request->header('x-tenant-id');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        // 3. From request parameter
        if (!$tenant) {
            $tenantId = $request->input('tenant_id');
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        //4. From authenticated user's current tenant
        if (!$tenant && auth()->check()) {
            $user = auth()->user();
            \Illuminate\Support\Facades\Log::info('TenantAware: Checking user for tenant', [
                'user_id' => $user->id,
                'current_tenant_id' => $user->current_tenant_id,
                'has_tenants' => $user->tenants()->exists()
            ]);

            $tenantId = $user->current_tenant_id;

            // Fallback to the first tenant the user belongs to if no current tenant is set
            if (!$tenantId && $user->tenants()->exists()) {
                $tenantId = $user->tenants()->first()->id;
                \Illuminate\Support\Facades\Log::info('TenantAware: Falling back to first tenant', [
                    'tenant_id' => $tenantId
                ]);
            }

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        if ($tenant) {
            // Initialize tenant in Stancl
            tenancy()->initialize($tenant);

            // Set tenant in request
            $request->merge(['current_tenant' => $tenant]);
            $request->attributes->set('current_tenant', $tenant);

            // Share tenant globally
            app()->instance('current_tenant', $tenant);

            // Set team ID for Spatie Permission
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            // Check user access
            $user = auth()->user();
            if ($user) {
                $hasAccess = $user->tenants()
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this tenant',
                        'code' => 403,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
