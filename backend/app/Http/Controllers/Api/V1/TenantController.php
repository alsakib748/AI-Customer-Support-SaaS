<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\TenantService;
use App\Services\AuditLogService;
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function current(Request $request)
    {
        try {
            $tenant = $request->attributes->get('current_tenant');

            if (!$tenant) {
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
                'message' => 'Failed to fetch tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new tenant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant details
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
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
                'message' => 'Failed to update tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch current tenant
     *
     * @param Request $request
     * @param string $tenantId
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Failed to switch tenant: ' . $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get all tenants for the current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Failed to fetch tenants: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tenant users
     *
     * @param Request $request
     * @param string $tenantId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request, $tenantId)
    {
        try {
            $users = $this->tenantService->getTenantUsers($tenantId);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invite user to tenant
     *
     * @param Request $request
     * @param string $tenantId
     * @return \Illuminate\Http\JsonResponse
     */
    public function inviteUser(Request $request, $tenantId)
    {
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
                'message' => 'Failed to invite user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove user from tenant
     *
     * @param Request $request
     * @param string $tenantId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeUser(Request $request, $tenantId, $userId)
    {
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
                'message' => 'Failed to remove user: ' . $e->getMessage(),
            ], 500);
        }
    }

}
