<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::info('Super Admin accessing route', [
                'user_id' => $user->id,
                'path' => $request->path(),
            ]);

            $request->attributes->set('is_super_admin', true);
            // Don't return here, allow tenant resolution to continue
        }


        $tenant = null;

        // 1. Try to get from the header or SSE query string.
        // EventSource cannot send custom headers, so streaming clients use tenant_id.
        $tenantId = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
        if ($tenantId) {
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found.',
                ], 404);
            }

            if (!$user->hasRole('super-admin') && !$user->hasTenantAccess($tenant->id)) {
                Log::warning('Tenant access denied', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this tenant.',
                ], 403);
            }

            Log::info('Tenant from header', ['tenant_id' => $tenantId]);
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

        // 4. Super Admin fallback to first available tenant in system
        if (!$tenant && $user && $user->hasRole('super-admin')) {
            $tenant = Tenant::first();
            if ($tenant) {
                Log::info('Super Admin fallback to first system tenant', ['tenant_id' => $tenant->id]);
            }
        }

        if ($tenant) {
            $request->attributes->set('current_tenant', $tenant);
            app()->instance('current_tenant', $tenant);
            try {
                tenancy()->initialize($tenant);
            } catch (\Exception $e) {
                Log::error('Tenancy initialization failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant initialization failed: ' . $e->getMessage(),
                ], 500);
            }
            Log::info('Tenant set in request', ['tenant_id' => $tenant->id]);
        } else {
            // Allow Super Admin to proceed without a tenant context
            if ($user && $user->hasRole('super-admin')) {
                Log::info('Super Admin proceeding without tenant context', [
                    'path' => $request->path(),
                ]);
                return $next($request);
            }

            Log::warning('No tenant resolved for request', [
                'path' => $request->path(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tenant context is required.',
            ], 403);
        }


        return $next($request);
    }
}
