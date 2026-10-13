<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    protected TenantService $tenantService;

    protected AuditLogService $auditLogService;

    public function __construct(TenantService $tenantService, AuditLogService $auditLogService)
    {
        $this->tenantService = $tenantService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get current tenant details
     *
     * @return JsonResponse
     */
    public function current(Request $request)
    {
        try {
            $tenant = $request->attributes->get('current_tenant');

            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No workspace found. Please select a workspace.',
                    'error' => 'tenant_not_found',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $tenant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenant: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new tenant
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $tenant = $this->tenantService->create($request->all(), auth()->id());

            $this->auditLogService->log(
                'tenant_created',
                'tenant',
                $tenant->id,
                null,
                ['name' => $tenant->name, 'slug' => $tenant->slug]
            );

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cross-tenant guard for routes that carry a tenant id in the URL.
     *
     * Authorization is evaluated against the CURRENT tenant (resolved by
     * TenantAware from the X-Tenant-ID header / current_tenant_id). Without this
     * check an actor with the relevant permission in tenant A could read or
     * mutate any other tenant by passing its id in the route. Super Admins are
     * allowed to manage any tenant.
     */
    private function authorizeTenantAccess(string $tenantId): ?JsonResponse
    {
        $actor = auth()->user();
        $currentTenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if ($actor && ! $actor->isSuperAdmin()) {
            if (! $currentTenant || (string) $currentTenant->id !== (string) $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this workspace.',
                ], 403);
            }
        }

        return null;
    }

    /**
     * Update tenant details
     *
     * @param  string  $id
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->authorizeTenantAccess($id)) {
            return $response;
        }

        try {
            $tenant = $this->tenantService->update($id, $request->all());

            $this->auditLogService->log(
                'tenant_updated',
                'tenant',
                $tenant->id,
                null,
                ['name' => $tenant->name]
            );

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch current tenant
     *
     * @param  string  $tenantId
     * @return JsonResponse
     */
    public function switchTenant(Request $request, $tenantId)
    {
        try {
            $tenant = $this->tenantService->switchTenant(auth()->id(), $tenantId);

            $this->auditLogService->log(
                'tenant_switched',
                'tenant',
                $tenant->id,
                null,
                ['new_tenant' => $tenant->name]
            );

            return response()->json([
                'success' => true,
                'message' => 'Switched to tenant successfully',
                'data' => $tenant,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch tenant: '.$e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get all tenants for the current user
     *
     * @return JsonResponse
     */
    public function getUserTenants(Request $request)
    {
        try {
            $tenants = auth()->user()->tenants;

            return response()->json([
                'success' => true,
                'data' => $tenants,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenants: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tenant users
     *
     * @param  string  $tenantId
     * @return JsonResponse
     */
    public function getUsers(Request $request, $tenantId)
    {
        if ($response = $this->authorizeTenantAccess($tenantId)) {
            return $response;
        }

        try {
            $users = $this->tenantService->getTenantUsers($tenantId);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invite user to tenant
     *
     * @param  string  $tenantId
     * @return JsonResponse
     */
    public function inviteUser(Request $request, $tenantId)
    {
        if ($response = $this->authorizeTenantAccess($tenantId)) {
            return $response;
        }

        try {
            $result = $this->tenantService->inviteUser($tenantId, $request->all());

            $this->auditLogService->log(
                'user_invited',
                'user',
                $result['user']->id,
                null,
                ['email' => $result['user']->email, 'role' => $result['role']]
            );

            return response()->json([
                'success' => true,
                'message' => 'User invited successfully',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to invite user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove user from tenant
     *
     * @param  string  $tenantId
     * @param  int  $userId
     * @return JsonResponse
     */
    public function removeUser(Request $request, $tenantId, $userId)
    {
        if ($response = $this->authorizeTenantAccess($tenantId)) {
            return $response;
        }

        try {
            $this->tenantService->removeUser($tenantId, $userId);

            $this->auditLogService->log(
                'user_removed_from_tenant',
                'user',
                $userId,
                null,
                ['tenant_id' => $tenantId]
            );

            return response()->json([
                'success' => true,
                'message' => 'User removed from tenant successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a team member's role within a tenant.
     *
     * @param  string  $tenantId
     * @param  int  $userId
     * @return JsonResponse
     */
    public function updateUserRole(Request $request, $tenantId, $userId)
    {
        if ($response = $this->authorizeTenantAccess($tenantId)) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'role' => 'required|string|in:admin,manager,agent,viewer',
            ]);

            $result = $this->tenantService->updateUserRole(
                $tenantId,
                (int) $userId,
                $validated['role']
            );

            $this->auditLogService->log(
                'user_role_updated',
                'user',
                (int) $userId,
                ['role' => $result['old_role']],
                ['role' => $result['new_role'], 'tenant_id' => $tenantId]
            );

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
