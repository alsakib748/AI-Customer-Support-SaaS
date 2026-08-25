<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
            'owner' => Permission::all(),
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
            ],
            'agent' => [
                'customers.view',
                'conversations.view',
                'conversations.reply',
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'knowledge.view',
            ],
            'viewer' => [
                'customers.view',
                'conversations.view',
                'tickets.view',
                'analytics.view',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'api']);

            $permissions = Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissions);
        }

        // Create super admin user (optional)
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('11111111'),
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $superAdmin->assignRole('owner');

    }
}
