<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Tenant Management
            'tenant.view',
            'tenant.create',
            'tenant.update',
            'tenant.delete',

            // User Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Conversation Management
            'conversations.view',
            'conversations.create',
            'conversations.update',
            'conversations.delete',
            'conversations.reply',
            'conversations.assign',

            // Ticket Management
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'tickets.assign',
            'tickets.resolve',

            // Customer Management
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',

            // Knowledge Base
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',

            // AI Configuration
            'ai.view',
            'ai.configure',
            'ai.manage',

            // Analytics
            'analytics.view',

            // Team Management
            'team.view',
            'team.invite',
            'team.update',
            'team.remove',

            // Billing
            'billing.view',
            'billing.manage',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create roles with permissions
        $roles = [
            'super-admin' => Permission::all()->pluck('name')->toArray(),
            'owner' => Permission::all()->pluck('name')->toArray(),
            'admin' => [
                'users.view',
                'users.create',
                'users.update',
                'conversations.view',
                'conversations.create',
                'conversations.update',
                'conversations.delete',
                'conversations.reply',
                'conversations.assign',
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.assign',
                'tickets.resolve',
                'customers.view',
                'customers.create',
                'customers.update',
                'knowledge.view',
                'knowledge.create',
                'knowledge.update',
                'ai.view',
                'ai.configure',
                'analytics.view',
                'team.view',
                'team.invite',
                'team.update',
                'profile.view',
                'profile.update',
            ],
            'manager' => [
                'users.view',
                'conversations.view',
                'conversations.create',
                'conversations.update',
                'conversations.reply',
                'conversations.assign',
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'tickets.assign',
                'customers.view',
                'customers.create',
                'customers.update',
                'knowledge.view',
                'knowledge.create',
                'knowledge.update',
                'analytics.view',
                'team.view',
                'profile.view',
                'profile.update',
            ],
            'agent' => [
                'customers.view',
                'conversations.view',
                'conversations.reply',
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'knowledge.view',
                'profile.view',
                'profile.update',
            ],
            'viewer' => [
                'customers.view',
                'conversations.view',
                'tickets.view',
                'analytics.view',
                'profile.view',
                'profile.update',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api',
            ]);

            $permissions = Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissions);
        }

        // Create super admin user (optional - for development)
        $superAdmin = User::where('email', 'superadmin@example.com')->first();

        if (!$superAdmin) {
            $superAdmin = User::create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@gmail.com',
                'password' => bcrypt('11111111'),
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]);
        }

        $superAdmin->assignRole('super-admin');

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Super Admin created: superadmin@gmail.com / 11111111');

    }
}
