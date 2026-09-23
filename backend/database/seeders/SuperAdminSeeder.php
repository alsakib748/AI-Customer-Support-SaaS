<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create/refresh the super-admin user with no tenant association.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'super-admin',
            'guard_name' => 'api',
        ]);

        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'alsakib.dev@gmail.com'],
            [
                'first_name' => 'Al',
                'last_name'  => 'Sakib',
                'username'   => 'alsakib',
                'email'      => 'alsakib.dev@gmail.com',
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

        $this->command->info('Super Admin ready: alsakib.dev@gmail.com / 11111111');
    }
}