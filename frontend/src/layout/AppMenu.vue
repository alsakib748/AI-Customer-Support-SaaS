<script setup>
import { ref } from 'vue';
import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        label: 'Home',
        items: [
            {
                label: 'Dashboard',
                icon: 'pi pi-fw pi-home',
                to: '/dashboard'
                // to: '/'
            }
        ]
    },
    {
        label: 'Analytics & Reporting',
        icon: 'pi pi-fw pi-briefcase',
        path: '/pages',
        items: [
            {
                label: 'Crud',
                icon: 'pi pi-fw pi-pencil',
                to: '/pages/crud'
            },
            {
                label: 'Auth',
                icon: 'pi pi-fw pi-user',
                path: '/auth',
                items: [
                    {
                        label: 'Login',
                        icon: 'pi pi-fw pi-sign-in',
                        to: '/login'
                    },
                    {
                        label: 'Error',
                        icon: 'pi pi-fw pi-times-circle',
                        to: '/auth/error'
                    },
                    {
                        label: 'Access Denied',
                        icon: 'pi pi-fw pi-lock',
                        to: '/auth/access'
                    }
                ]
            },
        ]
    },
    {
        label: 'Platform',
        items: [
            {
                label: 'Platform Analytics',
                icon: 'pi pi-chart-line',
                to: '/admin/analytics',
                // superAdminOnly: true,
            },
        ],
    },
    {
        label: 'Billing',
        items: [
            {
                label: 'Overview',
                icon: 'pi pi-credit-card',
                to: '/billing',
                // superAdminOnly: true,
            },
            {
                label: 'Invoices',
                icon: 'pi pi-file',
                to: '/billing/invoices',
                // superAdminOnly: true,
            },
            {
                label: 'Payments',
                icon: 'pi pi-wallet',
                to: '/billing/payments',
                // superAdminOnly: true,
            },
        ],
    },
    {
        label: 'Analytics & Reporting',
        icon: 'pi pi-chart-bar',
        // permission: ['analytics.view'],
        items: [
            {
                label: 'Overview',
                icon: 'pi pi-th-large',
                to: '/analytics/overview',
                // permission: ['analytics.view'],
            },
            {
                label: 'My Analytics',
                icon: 'pi pi-user',
                to: '/analytics/my',
                // permission: ['analytics.view'],
            },
            {
                label: 'Export History',
                icon: 'pi pi-download',
                to: '/analytics/exports',
                // permission: ['analytics.export'],
            },
            {
                label: 'Conversations',
                icon: 'pi pi-comments',
                to: '/analytics/conversations',
                // permission: ['analytics.conversations'],
            },
            {
                label: 'Customers',
                icon: 'pi pi-users',
                to: '/analytics/customers',
                // permission: ['analytics.customers'],
            },
            {
                label: 'Team Performance',
                icon: 'pi pi-user-edit',
                to: '/analytics/agents',
                // permission: ['analytics.agents'],
            },
            {
                label: 'Tickets',
                icon: 'pi pi-ticket',
                to: '/analytics/tickets',
                // permission: ['analytics.tickets'],
            },
            {
                label: 'AI Usage',
                icon: 'pi pi-sparkles',
                to: '/analytics/ai',
                // permission: ['analytics.ai'],
            },
            {
                label: 'Chat Widget',
                icon: 'pi pi-comment',
                to: '/analytics/widget',
                // permission: ['analytics.widget'],
            },
            {
                label: 'Knowledge Base',
                icon: 'pi pi-book',
                to: '/analytics/knowledge-base',
                // permission: ['analytics.knowledge_base'],
            },
        ]
    },
    {
        // label: 'Analytics & Reporting',
        items: [
            {
                label: 'Analytics',
                icon: 'pi pi-fw pi-home',
                to: '/analytics'
            }
        ]
    },
    {
        label: 'Main Module',
        items: [
            {
                label: 'Workspace',
                icon: 'pi pi-fw pi-building',
                to: '/settings/workspace'
            }
        ]
    },
    {
        // label: 'Team Management',
        items: [
            {
                label: 'Team Management',
                icon: 'pi pi-fw pi-slack',
                to: '/team/members'
            }
        ]
    },
    {
        // label: 'Customers Management',
        items: [
            {
                label: 'Customers Management',
                icon: 'pi pi-fw pi-users',
                to: '/customers'
            }
        ]
    },
    {
        // label: 'Customers Management',
        items: [
            {
                label: 'Conversation',
                icon: 'pi pi-fw pi-send',
                to: '/conversations'
            }
        ]
    },
    {
        // label: 'Customers Management',
        items: [
            {
                label: 'Tickets',
                icon: 'pi pi-fw pi-ticket',
                to: '/tickets'
            }
        ]
    },
    {
        // label: 'Customers Management',
        items: [
            {
                label: 'Chat Widget',
                icon: 'pi pi-fw pi-comments',
                to: '/settings/chat-widgets'
            }
        ]
    },
    {
        // label: 'Customers Management',
        items: [
            {
                label: 'KnowledgeBase',
                icon: 'pi pi-fw pi-book',
                to: '/knowledge-base'
            }
        ]
    },
    {
        label: 'AI',
        icon: 'pi pi-fw pi-user',
        // path: '/auth',
        items: [
            {
                label: 'AI Settings',
                icon: 'pi pi-cog',
                to: '/settings/ai'
            },
            {
                label: 'AI Analytics',
                icon: 'pi pi-chart-bar',
                to: '/ai/analytics'
            }
        ]
    },
    {
        label: 'UI Components',
        path: '/uikit',
        items: [
            {
                label: 'Form Layout',
                icon: 'pi pi-fw pi-id-card',
                to: '/uikit/formlayout'
            },
            {
                label: 'Input',
                icon: 'pi pi-fw pi-check-square',
                to: '/uikit/input'
            },
            {
                label: 'Button',
                icon: 'pi pi-fw pi-mobile',
                to: '/uikit/button',
                class: 'rotated-icon'
            },
            {
                label: 'Table',
                icon: 'pi pi-fw pi-table',
                to: '/uikit/table'
            },
            {
                label: 'List',
                icon: 'pi pi-fw pi-list',
                to: '/uikit/list'
            },
            {
                label: 'Tree',
                icon: 'pi pi-fw pi-share-alt',
                to: '/uikit/tree'
            },
            {
                label: 'Panel',
                icon: 'pi pi-fw pi-tablet',
                to: '/uikit/panel'
            },
            {
                label: 'Overlay',
                icon: 'pi pi-fw pi-clone',
                to: '/uikit/overlay'
            },
            {
                label: 'Media',
                icon: 'pi pi-fw pi-image',
                to: '/uikit/media'
            },
            {
                label: 'Menu',
                icon: 'pi pi-fw pi-bars',
                to: '/uikit/menu'
            },
            {
                label: 'Message',
                icon: 'pi pi-fw pi-comment',
                to: '/uikit/message'
            },
            {
                label: 'File',
                icon: 'pi pi-fw pi-file',
                to: '/uikit/file'
            },
            {
                label: 'Chart',
                icon: 'pi pi-fw pi-chart-bar',
                to: '/uikit/charts'
            },
            {
                label: 'Timeline',
                icon: 'pi pi-fw pi-calendar',
                to: '/uikit/timeline'
            },
            {
                label: 'Misc',
                icon: 'pi pi-fw pi-circle',
                to: '/uikit/misc'
            }
        ]
    },
    {
        label: 'Prime Blocks',
        icon: 'pi pi-fw pi-prime',
        path: '/blocks',
        items: [
            {
                label: 'Free Blocks',
                icon: 'pi pi-fw pi-eye',
                to: '/blocks/free'
            },
            {
                label: 'All Blocks',
                icon: 'pi pi-fw pi-globe',
                url: 'https://blocks.primevue.org/',
                target: '_blank'
            }
        ]
    },
    {
        label: 'Pages',
        icon: 'pi pi-fw pi-briefcase',
        path: '/pages',
        items: [
            {
                label: 'Landing',
                icon: 'pi pi-fw pi-globe',
                to: '/landing'
            },
            {
                label: 'Auth',
                icon: 'pi pi-fw pi-user',
                path: '/auth',
                items: [
                    {
                        label: 'Login',
                        icon: 'pi pi-fw pi-sign-in',
                        to: '/login'
                    },
                    {
                        label: 'Error',
                        icon: 'pi pi-fw pi-times-circle',
                        to: '/auth/error'
                    },
                    {
                        label: 'Access Denied',
                        icon: 'pi pi-fw pi-lock',
                        to: '/auth/access'
                    }
                ]
            },
            {
                label: 'Crud',
                icon: 'pi pi-fw pi-pencil',
                to: '/pages/crud'
            },
            {
                label: 'Not Found',
                icon: 'pi pi-fw pi-exclamation-circle',
                to: '/pages/notfound'
            },
            {
                label: 'Empty',
                icon: 'pi pi-fw pi-circle-off',
                to: '/pages/empty'
            }
        ]
    },
    {
        label: 'Hierarchy',
        icon: 'pi pi-fw pi-align-left',
        path: '/hierarchy',
        items: [
            {
                label: 'Submenu 1',
                icon: 'pi pi-fw pi-align-left',
                path: '/submenu_1',
                items: [
                    {
                        label: 'Submenu 1.1',
                        icon: 'pi pi-fw pi-align-left',
                        path: '/submenu_1_1',
                        items: [
                            {
                                label: 'Submenu 1.1.1',
                                icon: 'pi pi-fw pi-align-left'
                            },
                            {
                                label: 'Submenu 1.1.2',
                                icon: 'pi pi-fw pi-align-left'
                            },
                            {
                                label: 'Submenu 1.1.3',
                                icon: 'pi pi-fw pi-align-left'
                            }
                        ]
                    },
                    {
                        label: 'Submenu 1.2',
                        icon: 'pi pi-fw pi-align-left',
                        path: '/submenu_1_2',
                        items: [
                            {
                                label: 'Submenu 1.2.1',
                                icon: 'pi pi-fw pi-align-left'
                            }
                        ]
                    }
                ]
            },
            {
                label: 'Submenu 2',
                icon: 'pi pi-fw pi-align-left',
                path: '/submenu_2',
                items: [
                    {
                        label: 'Submenu 2.1',
                        icon: 'pi pi-fw pi-align-left',
                        path: '/submenu_2_1',
                        items: [
                            {
                                label: 'Submenu 2.1.1',
                                icon: 'pi pi-fw pi-align-left'
                            },
                            {
                                label: 'Submenu 2.1.2',
                                icon: 'pi pi-fw pi-align-left'
                            }
                        ]
                    },
                    {
                        label: 'Submenu 2.2',
                        icon: 'pi pi-fw pi-align-left',
                        path: '/submenu_2_2',
                        items: [
                            {
                                label: 'Submenu 2.2.1',
                                icon: 'pi pi-fw pi-align-left'
                            }
                        ]
                    }
                ]
            }
        ]
    },
    {
        label: 'Get Started',
        path: '/start',
        items: [
            {
                label: 'Documentation',
                icon: 'pi pi-fw pi-book',
                to: '/start/documentation'
            },
            {
                label: 'View Source',
                icon: 'pi pi-fw pi-github',
                url: 'https://github.com/primefaces/sakai-vue',
                target: '_blank'
            }
        ]
    }
]);


</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
