// src/stores/users.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import userService from '@/services/userService';

export const useUsersStore = defineStore('users', () => {
    // =========================================================
    // STATE
    // =========================================================
    const users = ref([]);
    const currentUser = ref(null);
    const stats = ref(null);
    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: 0,
        to: 0
    });

    const filters = ref({
        search: '',
        status: null,
        scope: null,
        verification: null,
        tenant_id: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    const loading = ref(false);
    const error = ref(null);

    // =========================================================
    // GETTERS
    // =========================================================
    const totalUsers = computed(() => pagination.value.total);
    const hasUsers = computed(() => users.value.length > 0);

    // =========================================================
    // ACTIONS — LIST
    // =========================================================
    const fetchUsers = async (params = {}) => {
        loading.value = true;
        error.value = null;

        const query = {
            per_page: filters.value.per_page,
            ...filters.value,
            ...params
        };

        Object.keys(query).forEach((key) => {
            if (query[key] === null || query[key] === '' || query[key] === undefined) {
                delete query[key];
            }
        });

        try {
            const response = await userService.getUsers(query);
            const meta = response.data.meta || {};
            users.value = response.data.data || [];
            pagination.value = {
                current_page: meta.current_page ?? 1,
                last_page: meta.last_page ?? 1,
                per_page: meta.per_page ?? 20,
                total: meta.total ?? 0,
                from: meta.from ?? 0,
                to: meta.to ?? 0
            };
            return response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load users';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchStats = async () => {
        try {
            const response = await userService.getUserStats();
            stats.value = response.data.data;
            return stats.value;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load user stats';
            throw err;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            scope: null,
            verification: null,
            tenant_id: null,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    // =========================================================
    // ACTIONS — SINGLE USER
    // =========================================================
    const fetchUser = async (id) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await userService.getUser(id);
            currentUser.value = response.data.data;
            return currentUser.value;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load user';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const clearCurrentUser = () => {
        currentUser.value = null;
    };

    // =========================================================
    // ACTIONS — CRUD
    // =========================================================
    const createUser = async (data) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await userService.createUser(data);
            await fetchUsers();
            await fetchStats().catch(() => {});
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to create user';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateUser = async (id, data) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await userService.updateUser(id, data);
            if (currentUser.value?.id === id) {
                currentUser.value = { ...response.data.data };
            }
            await fetchUsers();
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to update user';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // ACTIONS — LIFECYCLE
    // =========================================================
    const applyResult = (user) => {
        if (currentUser.value?.id === user?.id) {
            currentUser.value = user;
        }
        fetchUsers().catch(() => {});
        fetchStats().catch(() => {});
        return user;
    };

    const activateUser = async (id) => {
        const response = await userService.activateUser(id);
        return applyResult(response.data.data);
    };

    const suspendUser = async (id, reason = null) => {
        const response = await userService.suspendUser(id, reason);
        return applyResult(response.data.data);
    };

    const revokeUserSessions = async (id, reason = null) => {
        const response = await userService.revokeUserSessions(id, reason);
        if (currentUser.value?.id === id) {
            currentUser.value = response.data.data || currentUser.value;
        }
        return response.data.data;
    };

    // =========================================================
    // RETURN
    // =========================================================
    return {
        // State
        users,
        currentUser,
        stats,
        pagination,
        filters,
        loading,
        error,

        // Getters
        totalUsers,
        hasUsers,

        // List
        fetchUsers,
        fetchStats,
        resetFilters,

        // Single
        fetchUser,
        clearCurrentUser,

        // CRUD
        createUser,
        updateUser,

        // Lifecycle
        activateUser,
        suspendUser,
        revokeUserSessions
    };
});
