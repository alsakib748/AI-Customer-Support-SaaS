<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
     /**
     * List roles for the current scope.
     * Super Admin sees all (global + tenant).
     * Tenant Owner/Admin sees only their tenant's roles.
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $isSuperAdmin = $actor->isSuperAdmin();
        $tenantId = getPermissionsTeamId();
        $teamKey = config('permission.column_names.team_foreign_key');

        $query = Role::with('permissions:id,name')
            ->withCount('permissions');

        if (!$isSuperAdmin) {
            // Tenant users only see global + their own roles
            $query->where(function ($q) use ($teamKey, $tenantId) {
                $q->whereNull($teamKey)
                  ->orWhere($teamKey, $tenantId);
            });
        }

        $roles = $query->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($role) => [
                'id'                => $role->id,
                'name'              => $role->name,
                'label'             => $this->humanize($role->name),
                'description'       => $role->description,
                'is_system'         => (bool) $role->is_system,
                'team_id'           => $role->{$teamKey},
                'is_global'         => $role->{$teamKey} === null,
                'permissions_count' => $role->permissions_count,
                'permissions'       => $role->permissions->pluck('name')->toArray(),
                'users_count'       => $role->users()->count(),
                'created_at'        => $role->created_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $roles,
            'meta'    => [
                'is_super_admin' => $isSuperAdmin,
                'team_id'        => $tenantId,
            ],
        ]);
    }

    /**
     * Get a single role with full permission list.
     */
    public function show(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request->user(), $role);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $role->id,
                'name'        => $role->name,
                'label'       => $this->humanize($role->name),
                'description' => $role->description,
                'is_system'   => (bool) $role->is_system,
                'team_id'     => $role->{config('permission.column_names.team_foreign_key')},
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Create a role.
     */
    public function store(Request $request)
    {
        $actor    = $request->user();
        $tenantId = getPermissionsTeamId();
        $teamKey  = config('permission.column_names.team_foreign_key');

        // Super Admin creates global roles; tenant admins create tenant roles
        $teamId = $actor->isSuperAdmin() ? null : $tenantId;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = DB::connection('central')->transaction(function () use ($data, $teamId, $teamKey) {
            $role = Role::create([
                'name'        => $data['name'],
                'guard_name'  => 'api',
                $teamKey      => $teamId,
                'description' => $data['description'] ?? null,
                'is_system'   => false,
                'sort_order'  => 99,
            ]);

            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data'    => ['id' => $role->id, 'name' => $role->name],
        ], 201);
    }

    /**
     * Update role metadata.
     */
    public function update(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request->user(), $role);

        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be modified.',
            ], 422);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', "unique:roles,name,{$role->id}"],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request->user(), $role);

        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted.',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a role that is assigned to users. Reassign them first.',
            ], 422);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    /**
     * Sync permissions for a role (main dashboard action).
     */
    public function syncPermissions(Request $request, Role $role)
    {
        $this->authorizeRoleAccess($request->user(), $role);

        if ($role->name === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'The super_admin role bypasses permissions and cannot be edited.',
            ], 422);
        }

        $data = $request->validate([
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated.',
            'data'    => [
                'role'        => $role->name,
                'permissions' => $role->fresh()->permissions->pluck('name')->toArray(),
            ],
        ]);
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    /**
     * Confirm the actor can manage this role.
     */
    protected function authorizeRoleAccess($actor, Role $role): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key');

        // Tenant users can only touch their own tenant's roles (not global ones)
        if ($role->{$teamKey} !== null && $role->{$teamKey} !== getPermissionsTeamId()) {
            abort(403, 'You do not have access to this role.');
        }

        if ($role->{$teamKey} === null && !$actor->isSuperAdmin()) {
            abort(403, 'Global roles can only be managed by Super Admin.');
        }
    }

    protected function humanize(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}