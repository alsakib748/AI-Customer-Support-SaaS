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

        // Check if user is Super Admin
        if ($user && $user->hasRole('super-admin')) {
            Log::info('Super Admin accessing route without tenant', [
                'user_id' => $user->id,
                'path' => $request->path(),
            ]);

            $request->attributes->set('is_super_admin', true);
            return $next($request);
        }


        $tenant = null;

        // 1. Try to get from header
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                Log::info('Tenant from header', ['tenant_id' => $tenantId]);
            }
        }

        // 2. Try to get from authenticated user
        if (!$tenant && $user) {
            $tenantId = $user->current_tenant_id;
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    Log::info('Tenant from user current_tenant_id', ['tenant_id' => $tenantId]);
                }
            }
        }

        // 3. Try to get first tenant of user
        if (!$tenant && $user) {
            $tenant = $user->tenants()->first();
            if ($tenant) {
                $user->update(['current_tenant_id' => $tenant->id]);
                Log::info('Tenant from first tenant', ['tenant_id' => $tenant->id]);
            }
        }

        if ($tenant) {
            $request->attributes->set('current_tenant', $tenant);
            app()->instance('current_tenant', $tenant);
            tenancy()->initialize($tenant);
            Log::info('Tenant set in request', ['tenant_id' => $tenant->id]);
        } else {
            Log::warning('No tenant resolved for request', [
                'path' => $request->path(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'headers' => $request->headers->all(),
            ]);
        }


        return $next($request);
    }
}
