<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    /**
     * Assign the platform-scoped super_admin role to the platform accounts
     * and guarantee they have no tenant association.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'super_admin',
            'guard_name' => 'api',
        ]);

        foreach (['alsakib@gmail.com', 'superadmin@gmail.com'] as $email) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => 'Platform',
                    'last_name'  => 'Admin',
                    'username'   => str($email)->before('@'),
                    'email'      => $email,
                    'password'   => '11111111',
                    'uuid'       => Str::uuid(),
                    'is_active'  => true,
                    'deleted_at' => null,
                ]
            );

            $user->syncRoles([$role]);

            // Guarantee no tenant association.
            $user->tenants()->detach();
            $user->update(['current_tenant_id' => null]);
        }

        $this->command->info('Super Admins ready: alsakib@gmail.com / 11111111 and superadmin@gmail.com / 11111111');
    }
}