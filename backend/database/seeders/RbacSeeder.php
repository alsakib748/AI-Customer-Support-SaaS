<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Single source of truth for the RBAC permission vocabulary and
     * role -> permission matrix. Run this before assigning roles to users.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'api';

        // ==================================================
        // PERMISSIONS (canonical vocabulary)
        // ==================================================
        $permissions = [
            // Dashboard
            'dashboard.view',

            // Workspace
            'workspace.view', 'workspace.update',

            // Team
            'team.view', 'team.invite', 'team.update', 'team.remove', 'team.assign_role',

            // Customers
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'customers.export', 'customers.block',

            // Conversations
            'conversations.view', 'conversations.create', 'conversations.update',
            'conversations.delete', 'conversations.reply', 'conversations.assign',
            'conversations.unassign', 'conversations.resolve', 'conversations.reopen',
            'conversations.close',

            // Messages
            'messages.view', 'messages.send', 'messages.internal_note', 'messages.delete',

            // Tickets
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete',
            'tickets.assign', 'tickets.unassign', 'tickets.comment',
            'tickets.resolve', 'tickets.reopen', 'tickets.close',

            // Knowledge Base
            'knowledge_base.view', 'knowledge_base.create', 'knowledge_base.update',
            'knowledge_base.delete', 'knowledge_base.publish', 'knowledge_base.archive',

            // AI
            'ai.view', 'ai.use', 'ai.configure', 'ai.analytics',

            // Widgets
            'widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete',
            'widgets.enable', 'widgets.disable', 'widgets.regenerate_key', 'widgets.analytics',

            // Analytics
            'analytics.view', 'analytics.conversations', 'analytics.customers',
            'analytics.agents', 'analytics.tickets', 'analytics.ai',
            'analytics.widget', 'analytics.knowledge_base', 'analytics.export',

            // Billing
            'billing.view', 'billing.manage', 'billing.subscription',
            'billing.invoices', 'billing.payments',

            // Platform (Super Admin only)
            'platform.dashboard', 'platform.tenants', 'platform.users',
            'platform.analytics', 'platform.billing', 'platform.settings',

            // Users module (granular — Super Admin only)
            'platform.users.view',
            'platform.users.create',
            'platform.users.update',
            'platform.users.activate',
            'platform.users.suspend',
            'platform.users.view_memberships',
            'platform.users.view_activity',
            'platform.users.revoke_sessions',
            'platform.billing.view', 'platform.billing.plans',
            'platform.billing.subscriptions', 'platform.billing.invoices',
            'platform.billing.payments', 'platform.billing.coupons',
            'platform.billing.analytics',
            'platform.audit_logs', 'platform.system_health',
            'platform.notifications', 'platform.profile',

            // Tenants module (granular — Super Admin only)
            'platform.tenants.view',
            'platform.tenants.create',
            'platform.tenants.update',
            'platform.tenants.activate',
            'platform.tenants.suspend',
            'platform.tenants.archive',
            'platform.tenants.restore',
            'platform.tenants.manage',
            'platform.tenants.view_members',
            'platform.tenants.view_usage',
            'platform.tenants.view_activity',
            'platform.tenants.transfer_owner',
        ];

        foreach ($permissions as $name) {
            $parts  = explode('.', $name);
            $module = $parts[0];
            $action = $parts[1] ?? '';

            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                [
                    'module'    => $module,
                    'label'     => ucwords(str_replace('_', ' ', $action)),
                    'is_system' => true,
                ]
            );
        }

        // Remove seeded permissions that are no longer part of the canonical
        // vocabulary (custom permissions created via the RBAC UI are kept).
        Permission::where('is_system', true)
            ->whereNotIn('name', $permissions)
            ->delete();

        // ==================================================
        // ROLES
        // ==================================================
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'support_agent', 'guard_name' => $guard]);

        // ==================================================
        // ROLE -> PERMISSION MAPPINGS
        // ==================================================
        $this->assignSuperAdminPermissions();
        $this->assignOwnerPermissions();
        $this->assignAdminPermissions();
        $this->assignManagerPermissions();
        $this->assignAgentPermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * All platform-scoped permissions. Super Admin is additionally
     * bypassed inside the permission middleware (see CheckPermission).
     */
    protected function assignSuperAdminPermissions(): void
    {
        Role::findByName('super_admin', 'api')->syncPermissions(
            Permission::where('guard_name', 'api')
                ->where('name', 'like', 'platform.%')
                ->pluck('name')
                ->toArray()
        );
    }

    /**
     * Owner: every tenant-scoped permission, nothing platform-scoped.
     */
    protected function assignOwnerPermissions(): void
    {
        Role::findByName('owner', 'api')->syncPermissions(
            Permission::where('guard_name', 'api')
                ->where(function ($q) {
                    $q->where('name', 'like', 'dashboard.%')
                        ->orWhere('name', 'like', 'workspace.%')
                        ->orWhere('name', 'like', 'team.%')
                        ->orWhere('name', 'like', 'customers.%')
                        ->orWhere('name', 'like', 'conversations.%')
                        ->orWhere('name', 'like', 'messages.%')
                        ->orWhere('name', 'like', 'tickets.%')
                        ->orWhere('name', 'like', 'knowledge_base.%')
                        ->orWhere('name', 'like', 'ai.%')
                        ->orWhere('name', 'like', 'widgets.%')
                        ->orWhere('name', 'like', 'analytics.%')
                        ->orWhere('name', 'like', 'billing.%');
                })
                ->pluck('name')
                ->toArray()
        );
    }

    /**
     * Admin = Owner minus billing ownership (no manage/subscription).
     */
    protected function assignAdminPermissions(): void
    {
        $ownerPermissions = Role::findByName('owner', 'api')
            ->permissions()
            ->pluck('name')
            ->toArray();

        $adminPermissions = array_values(array_diff($ownerPermissions, [
            'billing.manage', 'billing.subscription',
        ]));

        Role::findByName('admin', 'api')->syncPermissions($adminPermissions);
    }

    /**
     * Manager: operational tenant role — no billing, no workspace ownership,
     * no team role assignment, no destructive customer operations.
     */
    protected function assignManagerPermissions(): void
    {
        Role::findByName('manager', 'api')->syncPermissions([
            'dashboard.view',

            'team.view', 'team.invite', 'team.update', 'team.remove',

            'customers.view', 'customers.create', 'customers.update', 'customers.export',

            'conversations.view', 'conversations.reply', 'conversations.assign',
            'conversations.unassign', 'conversations.resolve', 'conversations.reopen',
            'conversations.close',

            'messages.view', 'messages.send', 'messages.internal_note',

            'tickets.view', 'tickets.create', 'tickets.update',
            'tickets.assign', 'tickets.unassign', 'tickets.comment',
            'tickets.resolve', 'tickets.reopen', 'tickets.close',

            'knowledge_base.view', 'knowledge_base.create', 'knowledge_base.update',

            'widgets.view', 'widgets.analytics',

            'ai.view', 'ai.use', 'ai.analytics',

            'analytics.view', 'analytics.conversations', 'analytics.customers',
            'analytics.agents', 'analytics.tickets', 'analytics.ai',
            'analytics.widget', 'analytics.export',
        ]);
    }

    /**
     * Support Agent: focused customer-support workspace, read-mostly.
     */
    protected function assignAgentPermissions(): void
    {
        Role::findByName('support_agent', 'api')->syncPermissions([
            'dashboard.view',

            'customers.view', 'customers.create', 'customers.update',

            'conversations.view', 'conversations.reply',
            'conversations.resolve', 'conversations.reopen',

            'messages.view', 'messages.send', 'messages.internal_note',

            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.comment',

            'knowledge_base.view',

            'widgets.view',

            'ai.view', 'ai.use',

            'analytics.view', 'analytics.conversations', 'analytics.customers',
        ]);
    }
}