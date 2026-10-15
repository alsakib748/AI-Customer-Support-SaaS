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
        $user = auth()->user();

        // Resolve the super-admin flag exactly once per request and share it with
        // downstream middleware so they don't repeat the Spatie role lookup.
        $isSuperAdmin = $user ? $user->hasRole('super_admin') : false;
        $request->attributes->set('is_super_admin', $isSuperAdmin);

        $tenant = null;

        // 1. Try to get from the header or SSE query string.
        // EventSource cannot send custom headers, so streaming clients use tenant_id.
        $tenantId = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
        if ($tenantId) {
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }

            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found.',
                ], 404);
            }

            if (! $isSuperAdmin && ! $user->hasTenantAccess($tenant->id)) {
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

            // Membership was verified here (or is implicit for super admins).
            $request->attributes->set('tenant_membership_verified', true);
        }

        // 2. Try to get from authenticated user
        if (! $tenant && $user) {
            $tenantId = $user->current_tenant_id;
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
            }
        }

        // 3. Try to get first tenant of user
        if (! $tenant && $user) {
            $tenant = $user->tenants()->first();
            if ($tenant) {
                $user->update(['current_tenant_id' => $tenant->id]);
                // The first-tenant lookup itself proves membership.
                $request->attributes->set('tenant_membership_verified', true);
            }
        }

        // 4. Super Admin fallback to first available tenant in system
        if (! $tenant && $user && $isSuperAdmin) {
            $tenant = Tenant::first();
        }

        if ($tenant) {
            // Suspended / archived tenants are blocked for everyone except Super Admin
            // (who may still use the Manage Tenant context).
            if ($user && ! $isSuperAdmin) {
                $path = $request->path();

                $allowedWhileSuspended = collect(config('billing.suspended_tenant_allowed_paths', [
                    'api/v1/auth',
                    'api/v1/subscription',
                    'api/v1/billing',
                    'api/v1/invoices',
                    'api/v1/payments',
                ]))->some(function ($prefix) use ($path) {
                    return str_starts_with($path, trim($prefix, '/')) || $path === trim($prefix, '/');
                });

                $forbiddenStatuses = [Tenant::STATUS_SUSPENDED, Tenant::STATUS_ARCHIVED];

                if (in_array($tenant->status, $forbiddenStatuses, true) && ! $allowedWhileSuspended) {
                    Log::warning('Tenant access blocked due to status', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenant->id,
                        'status' => $tenant->status,
                        'path' => $request->path(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'This tenant is '.str_replace('_', ' ', $tenant->status).' and cannot be accessed.',
                    ], 403);
                }
            }

            $request->attributes->set('current_tenant', $tenant);
            app()->instance('current_tenant', $tenant);
            try {
                tenancy()->initialize($tenant);
            } catch (\Exception $e) {
                Log::error('Tenancy initialization failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Tenant initialization failed: '.$e->getMessage(),
                ], 500);
            }
        } else {
            // Allow Super Admin to proceed without a tenant context
            if ($isSuperAdmin) {
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
