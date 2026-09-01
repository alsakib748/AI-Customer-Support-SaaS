import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';
import router from '@/router';
import { toast } from 'vue3-toastify';
// import { Password } from 'primevue';

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref(null);
    const token = ref(null);
    const currentTenant = ref(null);
    const tenants = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const errors = ref(null);
    const initialized = ref(false);
    const redirectPath = ref(null);

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isSuperAdmin = computed(() => user.value?.roles?.includes('super-admin'));
    const currentTenantId = computed(() => currentTenant.value?.id);
    const userFullName = computed(() => user.value?.full_name || user.value?.email);
    const userPermissions = computed(() => user.value?.permissions || []);

    // Actions

    const setRedirectPath = (path) => {
        redirectPath.value = path;
    };

    const getRedirectPath = () => {
        const path = redirectPath.value || '/dashboard';
        redirectPath.value = null;
        return path;
    };

    const setAuth = (data) => {
        token.value = data.token;
        user.value = data.user;
        currentTenant.value = data.tenant || data.tenants?.[0] || null;
        tenants.value = data.tenants || [];

        // Save to localStorage
        localStorage.setItem('auth_token', data.token);
        if (currentTenant.value?.id) {
            localStorage.setItem('current_tenant_id', currentTenant.value.id);
        }

        // Set default authorization header
        api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
    };

    const clearAuth = () => {
        token.value = null;
        user.value = null;
        currentTenant.value = null;
        tenants.value = [];
        localStorage.removeItem('auth_token');
        localStorage.removeItem('current_tenant_id');
        delete api.defaults.headers.common['Authorization'];
    };

    const register = async (userData) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/register', userData);
            const data = response.data.data;
            setAuth(data);

            // Redirect to dashboard
            router.push('/dashboard');

            toast.success(`Welcome back, ${data.user.first_name}`);

            return data;
        } catch (err) {
            // Error toast is fired by the axios response interceptor in services/api.js
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Registration failed';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const login = async (credentials) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/login', credentials);

            // console.log('Login Response: ', response.data);

            if (response.data.success) {
                const data = response.data.data;

                setAuth(data);

                // Redirect to dashboard
                // router.push('/dashboard');

                // Redirect to intended page or dashboard
                const redirectTo = getRedirectPath();
                router.push(redirectTo);

                toast.success(`Welcome back, ${data.user.first_name}`);

                return data;
            }
        } catch (error) {
            // Error toast is fired by the axios response interceptor in services/api.js
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const logout = async () => {
        loading.value = true;

        try {
            await api.post('/auth/logout');
        } catch (err) {
            console.error('Logout error: ', err);
        } finally {
            clearAuth();
            router.push('/login');
            loading.value = false;
        }
    };

    const refreshToken = async () => {
        try {
            const response = await api.post('/auth/refresh');
            const newToken = response.data.data.token;
            token.value = newToken;
            localStorage.setItem('auth_token', newToken);
            return newToken;
        } catch (err) {
            clearAuth();
            throw err;
        }
    };

    const fetchUser = async () => {
        loading.value = true;

        try {
            const response = await api.get('/auth/me');
            const data = response.data.data;

            user.value = data.user;
            currentTenant.value = data.tenant;
            tenants.value = data.tenants || [];

            return data;
        } catch (err) {
            if (err.response?.status === 401) {
                clearAuth();
                router.push('/login');
            }
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const switchTenant = async (tenantId) => {
        loading.value = true;

        try {
            const response = await api.post(`/tenants/switch/${tenantId}`);
            const tenant = response.data.data;

            currentTenant.value = tenant;
            user.value.current_tenant_id = tenant.id;

            // Update tenant is localStorage
            localStorage.setItem('current_tenant_id', tenant.id);

            return tenant;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to switch tenant';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const changePassword = async (passwords) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/change-password', passwords);
            const newToken = response.data.data.token;
            token.value = newToken;
            localStorage.setItem('auth_token', newToken);

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Failed to change password';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Initialize from localStorage
    const init = async () => {
        const savedToken = localStorage.getItem('auth_token');

        if (!savedToken) {
            initialized.value = true;
            return;
        }

        token.value = savedToken;

        try {
            await fetchUser();
        } catch {
            clearAuth();
        } finally {
            initialized.value = true;
        }

        // if (savedToken) {
        //     token.value = savedToken;
        //     // Fetch user data
        //     fetchUser().catch(() => {
        //         clearAuth();
        //     });
        // }
    };

    // Has permission helper
    // const hasPermission = (permission) => {
    //     if (isSuperAdmin.value) return true;
    //     return permissions.some((p) => userPermissions.value.includes(p));
    // };

    // Has any permission helper
    // const hasAnyPermission = (permissions) => {
    //     if (isSuperAdmin.value) return true;
    //     return permissions.some((p) => userPermissions.value.includes(p));
    // };

    // Has all permissions helper
    // const hasAllPermissions = (permissions) => {
    //     if (isSuperAdmin.value) return true;
    //     return permissions.every((p) => userPermissions.value.includes(p));
    // };

    return {
        // State
        user,
        token,
        currentTenant,
        tenants,
        loading,
        error,
        errors,
        initialized,
        redirectPath,

        // Getters
        isAuthenticated,
        isSuperAdmin,
        currentTenantId,
        userFullName,
        userPermissions,

        // Actions
        register,
        login,
        logout,
        refreshToken,
        fetchUser,
        switchTenant,
        changePassword,
        init,
        clearAuth,
        setAuth,
        setRedirectPath,
        getRedirectPath
        // hasPermission,
        // hasAnyPermission,
        // hasAllPermissions
    };
});
