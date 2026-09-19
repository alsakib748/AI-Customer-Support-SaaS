import router from './router.js';
import { useAuthStore } from '@/stores/auth.js';

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ path: '/login', query: { redirect: to.fullPath } });
    }

    if (to.meta.requiresSuperAdmin) {
        const user = authStore.user;
        const isSuperAdmin = user?.role === 'super-admin' || (Array.isArray(user?.roles) && user.roles.includes('super-admin'));

        if (!isSuperAdmin) {
            return next({ path: '/403' });
        }
    }

    next();
});
