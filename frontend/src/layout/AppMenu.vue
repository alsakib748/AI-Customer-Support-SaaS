<script setup>
import { computed } from 'vue';
import AppMenuItem from './AppMenuItem.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

/**
 * Tenant / workspace menu — shown to owner, admin, manager and support_agent.
 * Every leaf carries a permission; the filter below hides what the user lacks.
 */
const tenantModel = () => [
    {
        label: 'Home',
        items: [
            {
                label: 'Dashboard',
                icon: 'pi pi-fw pi-home',
                to: '/dashboard',
                permission: 'dashboard.view'
            }
        ]
    },
    {
        label: 'Workspace',
        items: [
            {
                label: 'Customers Management',
                icon: 'pi pi-fw pi-users',
                to: '/customers',
                permission: 'customers.view'
            },
            {
                label: 'Conversation',
                icon: 'pi pi-fw pi-send',
                to: '/conversations',
                permission: 'conversations.view'
            },
            {
                label: 'Tickets',
                icon: 'pi pi-fw pi-ticket',
                to: '/tickets',
                permission: 'tickets.view'
            },
            {
                label: 'Chat Widget',
                icon: 'pi pi-fw pi-comments',
                to: '/settings/chat-widgets',
                permission: 'widgets.view'
            },
            {
                label: 'KnowledgeBase',
                icon: 'pi pi-fw pi-book',
                to: '/knowledge-base',
                permission: 'knowledge_base.view'
            }
        ]
    },
    {
        label: 'AI',
        icon: 'pi pi-fw pi-user',
        items: [
            {
                label: 'AI Settings',
                icon: 'pi pi-cog',
                to: '/settings/ai',
                permission: 'ai.configuration'
            },
            {
                label: 'AI Analytics',
                icon: 'pi pi-chart-bar',
                to: '/ai/analytics',
                permission: 'ai.analytics'
            }
        ]
    },
    {
        label: 'Analytics & Reporting',
        icon: 'pi pi-chart-bar',
        items: [
            {
                label: 'Overview',
                icon: 'pi pi-th-large',
                to: '/analytics/overview',
                permission: 'analytics.view'
            },
            {
                label: 'My Analytics',
                icon: 'pi pi-user',
                to: '/analytics/my',
                permission: 'analytics.view'
            },
            {
                label: 'Export History',
                icon: 'pi pi-download',
                to: '/analytics/exports',
                permission: 'analytics.export'
            },
            {
                label: 'Conversations',
                icon: 'pi pi-comments',
                to: '/analytics/conversations',
                permission: 'analytics.conversations'
            },
            {
                label: 'Customers',
                icon: 'pi pi-users',
                to: '/analytics/customers',
                permission: 'analytics.customers'
            },
            {
                label: 'Team Performance',
                icon: 'pi pi-user-edit',
                to: '/analytics/agents',
                permission: 'analytics.agents'
            },
            {
                label: 'Tickets',
                icon: 'pi pi-ticket',
                to: '/analytics/tickets',
                permission: 'analytics.tickets'
            },
            {
                label: 'AI Usage',
                icon: 'pi pi-sparkles',
                to: '/analytics/ai',
                permission: 'analytics.ai'
            },
            {
                label: 'Chat Widget',
                icon: 'pi pi-comment',
                to: '/analytics/widget',
                permission: 'analytics.widget'
            },
            {
                label: 'Knowledge Base',
                icon: 'pi pi-book',
                to: '/analytics/knowledge-base',
                permission: 'analytics.knowledge_base'
            }
        ]
    },
    {
        label: 'Team',
        items: [
            {
                label: 'Members',
                icon: 'pi pi-fw pi-slack',
                to: '/team/members',
                permission: 'team.view'
            }
        ]
    },
    {
        label: 'Billing',
        items: [
            {
                label: 'Overview',
                icon: 'pi pi-credit-card',
                to: '/billing',
                permission: 'billing.view'
            },
            {
                label: 'Plans',
                icon: 'pi pi-list',
                to: '/billing/plans',
                permission: 'billing.view'
            },
            {
                label: 'Usage',
                icon: 'pi pi-chart-bar',
                to: '/billing/usage',
                permission: 'billing.view'
            },
            {
                label: 'Invoices',
                icon: 'pi pi-file',
                to: '/billing/invoices',
                permission: 'billing.invoices'
            },
            {
                label: 'Payments',
                icon: 'pi pi-wallet',
                to: '/billing/payments',
                permission: 'billing.payments'
            }
        ]
    },
    {
        label: 'Workspace Settings',
        items: [
            {
                label: 'Workspace',
                icon: 'pi pi-fw pi-building',
                to: '/settings/workspace',
                permission: 'workspace.view'
            }
        ]
    }
];

/**
 * Platform / Super Admin menu — completely separate from tenant menus.
 * Filtered with the same permission helpers as the tenant menu, so the rule is
 * always: Laravel permission -> /me -> Pinia -> menu/router/buttons.
 */
const platformModel = () => [
    {
        label: 'Dashboard',
        items: [
            {
                label: 'Dashboard',
                icon: 'pi pi-fw pi-home',
                to: '/dashboard',
                permission: 'platform.dashboard'
            }
        ]
    },
    {
        label: 'Platform',
        items: [
            {
                label: 'Tenants',
                icon: 'pi pi-fw pi-building',
                to: '/admin/tenants',
                permission: 'platform.tenants'
            },
            {
                label: 'Users',
                icon: 'pi pi-fw pi-users',
                to: '/admin/users',
                permission: 'platform.users'
            },
            {
                label: 'Platform Analytics',
                icon: 'pi pi-fw pi-chart-bar',
                to: '/admin/analytics',
                permission: 'platform.analytics'
            }
        ]
    },
    {
        label: 'Billing',
        items: [
            {
                label: 'Billing Overview',
                icon: 'pi pi-credit-card',
                to: '/admin/billing',
                permission: 'platform.billing.view'
            },
            {
                label: 'Plans',
                icon: 'pi pi-box',
                to: '/admin/billing/plans',
                permission: 'platform.billing.plans'
            },
            {
                label: 'Subscriptions',
                icon: 'pi pi-users',
                to: '/admin/billing/subscriptions',
                permission: 'platform.billing.subscriptions'
            },
            {
                label: 'Invoices',
                icon: 'pi pi-file-edit',
                to: '/admin/billing/invoices',
                permission: 'platform.billing.invoices'
            },
            {
                label: 'Payments',
                icon: 'pi pi-money-bill',
                to: '/admin/billing/payments',
                permission: 'platform.billing.payments'
            },
            {
                label: 'Coupons',
                icon: 'pi pi-ticket',
                to: '/admin/billing/coupons',
                permission: 'platform.billing.coupons'
            },
            {
                label: 'Billing Analytics',
                icon: 'pi pi-chart-pie',
                to: '/admin/billing/analytics',
                permission: 'platform.billing.analytics'
            }
        ]
    },
    {
        label: 'Access & Security',
        items: [
            {
                label: 'Roles & Permissions',
                icon: 'pi pi-shield',
                to: '/admin/rbac',
                permission: 'platform.settings'
            },
            {
                label: 'Audit Logs',
                icon: 'pi pi-history',
                to: '/admin/audit-logs',
                permission: 'platform.audit_logs'
            }
        ]
    },
    {
        label: 'System',
        items: [
            {
                label: 'System Settings',
                icon: 'pi pi-cog',
                to: '/admin/settings',
                permission: 'platform.settings'
            },
            {
                label: 'System Health',
                icon: 'pi pi-heart',
                to: '/admin/system-health',
                permission: 'platform.system_health'
            },
            {
                label: 'Notifications',
                icon: 'pi pi-bell',
                to: '/admin/notifications',
                permission: 'platform.notifications'
            }
        ]
    },
    {
        label: 'Account',
        items: [
            {
                label: 'My Profile',
                icon: 'pi pi-user',
                to: '/admin/profile',
                permission: 'platform.profile'
            },
            {
                label: 'Logout',
                icon: 'pi pi-sign-out',
                command: () => auth.logout()
            }
        ]
    }
];

/**
 * The scope decides which sidebar is shown: platform vs tenant. The permission
 * filter then hides anything the current user can't see within that scope.
 */
const model = computed(() => (auth.scope === 'platform' ? platformModel() : tenantModel()));

const visibleMenu = computed(() => {
    return model.value
        .map((group) => {
            const items = group.items.filter((item) => {
                return !item.permission || auth.hasPermission(item.permission);
            });

            if (items.length === 0) return null;
            return { ...group, items };
        })
        .filter(Boolean);
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in visibleMenu" :key="i">
            <app-menu-item v-if="!item.separator" :item="item" :index="i" />
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
