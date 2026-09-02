import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'Login',
            component: () => import('@/views/pages/auth/Login.vue'),
            meta: {
                requiresGuest: true,
                title: 'Login'
            }
        },
        {
            path: '/register',
            name: 'Register',
            component: () => import('@/views/pages/auth/Register.vue'),
            meta: {
                requiresGuest: true,
                title: 'Register'
            }
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/views/pages/auth/Access.vue'),
            meta: {
                requiresGuest: true,
                title: 'Access Denied'
            }
        },
        {
            path: '/auth/error',
            name: 'error',
            component: () => import('@/views/pages/auth/Error.vue'),
            meta: {
                requiresGuest: true,
                title: 'Error'
            }
        },
        // {
        //     path: '/setup/create-workspace',
        //     name: 'CreateWorkspace',
        //     component: () => import('@/views/pages/setup/CreateWorkspace.vue'),
        //     meta: {
        //         requiresAuth: false,
        //         title: 'Create Workspace'
        //     }
        // },
        {
            path: '/',

            component: AppLayout,
            meta: {
                requiresAuth: true
            },
            children: [
                // Protected routes
                {
                    path: '',
                    redirect: '/dashboard'
                },
                {
                    path: '/dashboard',
                    name: 'Dashboard',
                    component: () => import('@/views/Dashboard.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/settings/workspace',
                    name: 'WorkspaceSettings',
                    component: () => import('@/views/settings/Workspace.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Workspace Settings'
                    }
                },
                {
                    path: '/team',
                    name: 'Team',
                    redirect: '/team/members'
                },
                {
                    path: '/team/members',
                    name: 'TeamMembers',
                    component: () => import('@/views/team/Members.vue'),
                    meta: {
                        requiresAuth: true,
                        title: 'Team Members'
                    }
                },
                {
                    path: '/uikit/formlayout',
                    name: 'formlayout',
                    component: () => import('@/views/uikit/FormLayout.vue')
                },
                {
                    path: '/uikit/input',
                    name: 'input',
                    component: () => import('@/views/uikit/InputDoc.vue')
                },
                {
                    path: '/uikit/button',
                    name: 'button',
                    component: () => import('@/views/uikit/ButtonDoc.vue')
                },
                {
                    path: '/uikit/table',
                    name: 'table',
                    component: () => import('@/views/uikit/TableDoc.vue')
                },
                {
                    path: '/uikit/list',
                    name: 'list',
                    component: () => import('@/views/uikit/ListDoc.vue')
                },
                {
                    path: '/uikit/tree',
                    name: 'tree',
                    component: () => import('@/views/uikit/TreeDoc.vue')
                },
                {
                    path: '/uikit/panel',
                    name: 'panel',
                    component: () => import('@/views/uikit/PanelsDoc.vue')
                },

                {
                    path: '/uikit/overlay',
                    name: 'overlay',
                    component: () => import('@/views/uikit/OverlayDoc.vue')
                },
                {
                    path: '/uikit/media',
                    name: 'media',
                    component: () => import('@/views/uikit/MediaDoc.vue')
                },
                {
                    path: '/uikit/message',
                    name: 'message',
                    component: () => import('@/views/uikit/MessagesDoc.vue')
                },
                {
                    path: '/uikit/file',
                    name: 'file',
                    component: () => import('@/views/uikit/FileDoc.vue')
                },
                {
                    path: '/uikit/menu',
                    name: 'menu',
                    component: () => import('@/views/uikit/MenuDoc.vue')
                },
                {
                    path: '/uikit/charts',
                    name: 'charts',
                    component: () => import('@/views/uikit/ChartDoc.vue')
                },
                {
                    path: '/uikit/misc',
                    name: 'misc',
                    component: () => import('@/views/uikit/MiscDoc.vue')
                },
                {
                    path: '/uikit/timeline',
                    name: 'timeline',
                    component: () => import('@/views/uikit/TimelineDoc.vue')
                },
                {
                    path: '/blocks/free',
                    name: 'blocks',
                    meta: {
                        breadcrumb: ['Prime Blocks', 'Free Blocks']
                    },
                    component: () => import('@/views/utilities/Blocks.vue')
                },
                {
                    path: '/pages/empty',
                    name: 'empty',
                    component: () => import('@/views/pages/Empty.vue')
                },
                {
                    path: '/pages/crud',
                    name: 'crud',
                    component: () => import('@/views/pages/Crud.vue')
                },
                {
                    path: '/start/documentation',
                    name: 'documentation',
                    component: () => import('@/views/pages/Documentation.vue')
                }
            ]
        },
        {
            path: '/landing',
            name: 'landing',
            component: () => import('@/views/pages/Landing.vue')
        },
        {
            path: '/pages/notfound',
            name: 'notfound',
            component: () => import('@/views/pages/NotFound.vue')
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            component: () => import('@/views/pages/auth/Error.vue')
        }
    ],
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { top: 0 };
        }
    }
});

// Navigation guards

router.beforeEach(async (to, form, next) => {
    // Set page title
    document.title = to.meta.title ? `${to.meta.title} | AI Customer Support Saas` : 'AI Customer Support SaaS';

    const authStore = useAuthStore();

    // Wait for auth to be initialized
    if (!authStore.initialized) {
        await authStore.init();
    }

    const isAuthenticated = authStore.isAuthenticated;

    // ============================================
    // RULE 1: Protected routes - Require authentication
    // ============================================
    if (to.meta.requiresAuth) {
        if (!isAuthenticated) {
            // Store the intended route for redirect after login
            authStore.setRedirectPath(to.fullPath);

            toast.warning('Please login to access this page', {
                autoClose: 3000,
                position: 'top-right'
            });

            return next({
                path: '/login',
                query: { redirect: to.fullPath }
            });
        }

        // Check if workspace is selected/exists
        // if (!authStore.currentTenantId && to.path !== '/setup/create-workspace' && to.path !== '/settings/workspace') {
        //     return next({
        //         path: '/setup/create-workspace',
        //         query: { redirect: to.fullPath }
        //     });
        // }

        return next();
    }

    // ============================================
    // RULE 2: Guest routes - Only for non-authenticated users
    // ============================================
    if (to.meta.requiresGuest) {
        if (isAuthenticated) {
            // Redirect authenticated users away from guest pages
            toast.info('You are already logged in', {
                autoClose: 3000,
                position: 'top-right'
            });
            return next('/dashboard');
        }
        return next();
    }

    // ============================================
    // RULE 3: Public routes - Allow all
    // ============================================
    next();
});

// After each navigation
router.afterEach((to, from) => {
    // Scroll to top on page change
    if (to.path !== from.path) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});

// Handle navigation errors
router.onError((error) => {
    console.error('Router error:', error);
});

export default router;
