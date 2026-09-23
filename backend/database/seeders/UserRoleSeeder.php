<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class UserRoleSeeder extends Seeder
{
    /**
     * Assign Spatie roles to existing users so the RBAC pipeline has data.
     *
     * - Platform accounts (no tenant) -> super_admin (team = null).
     * - Tenant members -> the global role matching their tenant_user pivot
     *   role, scoped to each tenant (model_has_roles.tenant_id = tenant).
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        // 1. Platform accounts
        User::whereIn('email', ['alsakib@gmail.com', 'superadmin@gmail.com'])->get()
            ->each(function (User $user) {
                setPermissionsTeamId(null);
                $user->syncRoles([Role::findByName('super_admin', 'api')]);
            });

        // 2. Tenant members — derive the role from the tenant_user pivot
        $roleMap = [
            'owner'          => 'owner',
            'admin'          => 'admin',
            'manager'        => 'manager',
            'agent'          => 'support_agent',
            'support_agent'  => 'support_agent',
            'super_admin'    => 'super_admin',
        ];

        TenantUser::query()
            ->whereNull('deleted_at')
            ->select('user_id', 'tenant_id', 'role')
            ->get()
            ->each(function (TenantUser $pivot) use ($roleMap) {
                $roleName = $roleMap[strtolower((string) $pivot->role)] ?? null;
                if (!$roleName) {
                    return;
                }

                $user = User::find($pivot->user_id);
                $role = Role::findByName($roleName, 'api');
                if (!$user || !$role) {
                    return;
                }

                if ($roleName === 'super_admin') {
                    setPermissionsTeamId(null);
                    $user->syncRoles([$role]);
                    $user->tenants()->detach($pivot->tenant_id);
                    return;
                }

                // Scope the role to the tenant where the membership lives.
                setPermissionsTeamId($pivot->tenant_id);
                $user->assignRole($role);
            });

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('User roles assigned from tenant_user pivots.');
    }
}