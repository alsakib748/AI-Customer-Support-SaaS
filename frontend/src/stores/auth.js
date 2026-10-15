import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';
import router from '@/router';
import { toast } from 'vue3-toastify';

export const useAuthStore = defineStore('auth', () => {
    // =========================================================
    // STATE
    // =========================================================
    const user = ref(null);
    const token = ref(localStorage.getItem('auth_token') || null);
    const currentTenant = ref(null);
    const tenants = ref([]);
    const scope = ref(null); // 'platform' | 'tenant'
    const role = ref(null); // 'owner' | 'admin' | 'manager' | 'support_agent' | 'super_admin'
    const permissions = ref([]); // ['customers.view', ...]

    const loading = ref(false);
    const error = ref(null);
    const errors = ref(null);
    const initialized = ref(false);
    const redirectPath = ref(null);

    // Restore token header on cold boot
    if (token.value) {
        api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    }

    // =========================================================
    // GETTERS
    // =========================================================
    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isSuperAdmin = computed(() => role.value === 'super_admin' || scope.value === 'platform');
    const isOwner = computed(() => role.value === 'owner');
    const isAdmin = computed(() => role.value === 'admin');
    const isManager = computed(() => role.value === 'manager');
    const isAgent = computed(() => role.value === 'support_agent');

    const currentTenantId = computed(() => currentTenant.value?.id);
    const userFullName = computed(() => user.value?.full_name || user.value?.email);
    const userPermissions = computed(() => permissions.value);

    // =========================================================
    // REDIRECT HELPERS
    // =========================================================
    const setRedirectPath = (path) => {
        redirectPath.value = path;
    };

    const getRedirectPath = () => {
        const path = redirectPath.value || '/dashboard';
        redirectPath.value = null;
        return path;
    };

    const clearRedirectPath = () => {
        redirectPath.value = null;
    };

    // =========================================================
    // PERMISSION HELPERS
    // =========================================================
    const hasPermission = (permission) => {
        if (!permission) return true;
        if (isSuperAdmin.value) return true; // Super Admin bypasses
        return permissions.value.includes(permission);
    };

    const hasAnyPermission = (perms) => {
        if (!Array.isArray(perms) || perms.length === 0) return true;
        if (isSuperAdmin.value) return true;
        return perms.some((p) => permissions.value.includes(p));
    };

    const hasAllPermissions = (perms) => {
        if (!Array.isArray(perms) || perms.length === 0) return true;
        if (isSuperAdmin.value) return true;
        return perms.every((p) => permissions.value.includes(p));
    };

    const hasRole = (r) => role.value === r;

    // =========================================================
    // INTERNAL — apply /me response to state
    // =========================================================
    const applyMe = (data) => {
        user.value = data.user ?? null;
        currentTenant.value = data.tenant ?? null;
        scope.value = data.scope ?? null;
        role.value = data.role ?? null;
        permissions.value = data.permissions ?? [];

        if (currentTenant.value?.id) {
            localStorage.setItem('current_tenant_id', currentTenant.value.id);
        }
    };

    // =========================================================
    // ACTIONS — auth lifecycle
    // =========================================================
    const setAuth = (data) => {
        token.value = data.token;
        user.value = data.user;

        if (data.token) {
            localStorage.setItem('auth_token', data.token);
            api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
        }

        // If the login response already includes tenant + permissions, apply them
        if (data.tenant || data.permissions || data.role) {
            applyMe({
                user: data.user,
                tenant: data.tenant,
                scope: data.scope,
                role: data.role,
                permissions: data.permissions
            });
        } else if (data.tenant || data.tenants?.[0]) {
            currentTenant.value = data.tenant || data.tenants[0];
        }

        if (data.tenants) tenants.value = data.tenants;
    };

    const clearAuth = () => {
        token.value = null;
        user.value = null;
        currentTenant.value = null;
        tenants.value = [];
        scope.value = null;
        role.value = null;
        permissions.value = [];
        redirectPath.value = null;

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

            // Email verification flow: the account exists but no token is
            // issued until the address is verified. Surface the account for
            // display and route the user to the "check your inbox" screen
            // instead of logging them in.
            if (data.email_verification_required && !data.token) {
                user.value = data.user ?? null;

                router.push({
                    name: 'VerifyEmail',
                    query: { email: data.user?.email }
                });

                toast.info('Please check your inbox to verify your email address.');

                return data;
            }

            setAuth(data);

            // Fetch full authorization context only when the response did not
            // already include it, then mark auth as initialized so the router
            // guard does not refetch /auth/me on the next navigation.
            if (!data.role || !Array.isArray(data.permissions)) {
                await fetchMe().catch(() => {});
            }
            initialized.value = true;

            router.push('/dashboard');
            toast.success(`Welcome, ${data.user.first_name}`);

            return data;
        } catch (err) {
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Registration failed';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const login = async (credentials) => {
        // Guard against duplicate submissions (double-click / Enter + click).
        // The login request must never be sent twice for the same intent.
        if (loading.value) return;

        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/login', credentials, { skipAuthRefresh: true });

            if (!response.data.success) {
                throw new Error('Login failed');
            }

            const data = response.data.data;
            setAuth(data);

            // The login response already carries the authorization context
            // (role, permissions, tenant), so only fall back to /auth/me if it
            // is missing. A successful login costs a single network request.
            if (!data.role || !Array.isArray(data.permissions)) {
                try {
                    await fetchMe();
                } catch (e) {
                    console.warn('Could not fetch /me after login:', e);
                }
            }

            // Mark auth as initialized so the router guard does not issue a
            // second /auth/me on the very next navigation.
            initialized.value = true;

            const redirectTo = getRedirectPath();
            router.push(redirectTo);
            toast.success(`Welcome back, ${data.user.first_name}`);

            return data;
        } catch (err) {
            if (err.response?.data?.errors) {
                errors.value = err.response.data.errors;
            }
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Logout — synchronous for router guards, fires the API call asynchronously.
     */
    const logout = () => {
        // Fire-and-forget server-side token invalidation
        api.post('/auth/logout').catch((err) => {
            console.warn('Logout API error:', err?.message);
        });

        clearAuth();
        router.push('/login');
    };

    const refreshToken = async () => {
        try {
            const response = await api.post('/auth/refresh', {}, { skipAuthRefresh: true });
            const newToken = response.data.data.token;

            token.value = newToken;
            localStorage.setItem('auth_token', newToken);
            api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;

            return newToken;
        } catch (err) {
            clearAuth();
            throw err;
        }
    };

    // =========================================================
    // ACTIONS — fetch /me
    // =========================================================
    const fetchMe = async () => {
        if (!token.value) return null;

        loading.value = true;

        try {
            const { data } = await api.get('/auth/me');

            if (!data.success) return null;

            applyMe(data.data);

            // Some backends also return tenants list
            if (data.data.tenants) tenants.value = data.data.tenants;

            return data.data;
        } catch (err) {
            if (err.response?.status === 401) {
                clearAuth();
            }
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Alias — same as fetchMe
    const fetchUser = fetchMe;

    const refreshPermissions = async () => {
        if (!token.value) return null;
        return fetchMe();
    };

    // =========================================================
    // TENANT SWITCHING
    // =========================================================
    const switchTenant = async (tenantId) => {
        loading.value = true;

        try {
            const response = await api.post(`/tenants/switch/${tenantId}`);
            const tenant = response.data.data;

            currentTenant.value = tenant;
            if (user.value) user.value.current_tenant_id = tenant.id;

            localStorage.setItem('current_tenant_id', tenant.id);

            // ✅ Re-fetch /me so role + permissions update for the new tenant
            await fetchMe().catch(() => {});

            toast.success(`Switched to ${tenant.name}`);

            return tenant;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to switch workspace';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // EMAIL VERIFICATION
    // =========================================================
    const verifyEmail = async (id, hash) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.get(`/auth/verify-email/${id}/${hash}`, {
                skipErrorToast: true
            });

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Email verification failed.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const resendVerification = async (email) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/resend-verification', { email }, { skipErrorToast: true });

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Could not send a verification email.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // CHANGE PASSWORD
    // =========================================================
    const changePassword = async (passwords) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/change-password', passwords);
            const newToken = response.data.data.token;

            if (newToken) {
                token.value = newToken;
                localStorage.setItem('auth_token', newToken);
                api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
            }

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Failed to change password';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // PASSWORD RESET
    // =========================================================
    const forgotPassword = async (payload) => {
        loading.value = true;
        error.value = null;

        try {
            const data = typeof payload === 'string' ? { email: payload } : payload;
            const response = await api.post('/auth/forgot-password', data);

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Could not send the reset link.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const resetPassword = async (payload) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.post('/auth/reset-password', payload);
            const data = response.data?.data ?? {};

            // The backend issues a fresh JWT after a successful reset so the
            // user is signed straight in rather than being sent back to login.
            if (data.token) {
                token.value = data.token;
                localStorage.setItem('auth_token', data.token);
                api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;

                await fetchMe().catch(() => {});
                initialized.value = true;
            }

            return response.data;
        } catch (err) {
            error.value = err.response?.data?.errors || err.response?.data?.message || 'Password reset failed.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // INIT (called once per app boot by router guard)
    // =========================================================
    const init = async () => {
        if (initialized.value) return;

        if (!token.value) {
            initialized.value = true;
            return;
        }

        try {
            await fetchMe();
        } catch (e) {
            console.error('auth.init failed:', e);
            clearAuth();
        } finally {
            initialized.value = true;
        }
    };

    // =========================================================
    // RETURN
    // =========================================================
    return {
        // State
        user,
        token,
        currentTenant,
        tenants,
        scope,
        role,
        permissions,
        loading,
        error,
        errors,
        initialized,
        redirectPath,

        // Getters
        isAuthenticated,
        isSuperAdmin,
        isOwner,
        isAdmin,
        isManager,
        isAgent,
        currentTenantId,
        userFullName,
        userPermissions,

        // Permission helpers
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,

        // Redirect helpers
        setRedirectPath,
        getRedirectPath,
        clearRedirectPath,

        // Actions
        setAuth,
        clearAuth,
        register,
        login,
        logout,
        refreshToken,
        fetchMe,
        fetchUser,
        refreshPermissions,
        switchTenant,
        changePassword,
        verifyEmail,
        resendVerification,
        forgotPassword,
        resetPassword,
        init,
        applyMe
    };
});
