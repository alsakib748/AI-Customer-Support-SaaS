<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\TenantRoleMap;
use App\Traits\GuardsSystemUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

class UserRoleController extends Controller
{
    use GuardsSystemUsers;

    /**
     * Assign a role to a user.
     */
    public function assign(Request $request, User $user)
    {
        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        $data = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $actor = $request->user();

        // Canonicalise the tenant-facing vocabulary (agent -> support_agent).
        // Unknown names fall back to the raw value so seeded/canonical roles
        // keep working unchanged.
        $roleName = TenantRoleMap::toSpatie($data['role']) ?? $data['role'];

        // Resolve the team the role must live in. Tenant actors already have
        // their team set by SetPermissionTeam; a platform admin assigning to a
        // tenant member must scope to that member's tenant.
        $previousTeamId = getPermissionsTeamId();
        $teamId = $previousTeamId;
        if ($actor->isSuperAdmin() && ! $teamId) {
            $teamId = $user->current_tenant_id ?? $user->tenants()->value('tenants.id');
        }
        setPermissionsTeamId($teamId);

        try {
            $role = Role::findByName($roleName, 'api');

            // Guard: super_admin only assignable by super_admin
            if ($role->name === 'super_admin' && ! $actor->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can assign the super_admin role.',
                ], 403);
            }

            // Guard: tenant users can only assign global or their-tenant roles
            if (! $actor->isSuperAdmin()) {
                $teamKey = config('permission.column_names.team_foreign_key');
                if ($role->{$teamKey} !== null && $role->{$teamKey} !== $teamId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You cannot assign this role.',
                    ], 403);
                }
            }

            DB::connection('central')->transaction(function () use ($user, $role) {
                $user->syncRoles([$role->name]);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        } catch (RoleDoesNotExist $e) {
            return response()->json([
                'success' => false,
                'message' => "The role '{$data['role']}' does not exist.",
            ], 422);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' assigned to {$user->email}.",
        ]);
    }

    /**
     * Revoke all roles from a user.
     */
    public function revoke(Request $request, User $user)
    {
        $actor = $request->user();

        $guard = $this->protectSystemUser($user);
        if ($guard) {
            return $guard;
        }

        if ($user->id === $actor->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot revoke your own role.',
            ], 422);
        }

        DB::connection('central')->transaction(function () use ($user) {
            $user->syncRoles([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        return response()->json(['success' => true, 'message' => 'Roles revoked.']);
    }
}
