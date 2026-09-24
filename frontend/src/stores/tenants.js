// src/stores/tenants.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import tenantService from '@/services/tenantService';

export const useTenantsStore = defineStore('tenants', () => {
    // =========================================================
    // STATE
    // =========================================================
    const tenants = ref([]);
    const currentTenant = ref(null);
    const plans = ref([]);
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
        plan_id: null,
        subscription_status: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    const loading = ref(false);
    const error = ref(null);

    // =========================================================
    // GETTERS
    // =========================================================
    const totalTenants = computed(() => pagination.value.total);
    const hasTenants = computed(() => tenants.value.length > 0);
    const isTenantLoading = computed(() => loading.value && !currentTenant.value);

    // =========================================================
    // ACTIONS — LIST
    // =========================================================
    const fetchTenants = async (params = {}) => {
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
            const response = await tenantService.getTenants(query);
            const meta = response.data.meta || {};
            tenants.value = response.data.data || [];
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
            error.value = err.response?.data?.message || 'Failed to load tenants';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            plan_id: null,
            subscription_status: null,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    // =========================================================
    // ACTIONS — SINGLE TENANT
    // =========================================================
    const fetchTenant = async (id) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await tenantService.getTenant(id);
            currentTenant.value = response.data.data;
            return currentTenant.value;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load tenant';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const clearCurrentTenant = () => {
        currentTenant.value = null;
    };

    // =========================================================
    // ACTIONS — PLANS (for the create dialog)
    // =========================================================
    const fetchPlans = async () => {
        try {
            const response = await tenantService.getTenantPlans();
            plans.value = response.data.data || [];
            return plans.value;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load plans';
            throw err;
        }
    };

    // =========================================================
    // ACTIONS — CRUD
    // =========================================================
    const createTenant = async (data) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await tenantService.createTenant(data);
            await fetchTenants();
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to create tenant';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateTenant = async (id, data) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await tenantService.updateTenant(id, data);
            if (currentTenant.value?.id === id) {
                currentTenant.value = { ...currentTenant.value, ...response.data.data };
            }
            await fetchTenants();
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to update tenant';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // ACTIONS — LIFECYCLE
    // =========================================================
    const applyStatusResponse = (response, refreshList = true) => {
        const tenant = response.data.data;
        if (currentTenant.value?.id === tenant?.id) {
            currentTenant.value = tenant;
        }
        if (refreshList) {
            fetchTenants().catch(() => {});
        }
        return tenant;
    };

    const activateTenant = async (id) => {
        const response = await tenantService.activateTenant(id);
        return applyStatusResponse(response);
    };

    const suspendTenant = async (id, reason = null) => {
        const response = await tenantService.suspendTenant(id, reason);
        return applyStatusResponse(response);
    };

    const archiveTenant = async (id, confirmation) => {
        const response = await tenantService.archiveTenant(id, confirmation);
        return applyStatusResponse(response);
    };

    const restoreTenant = async (id) => {
        const response = await tenantService.restoreTenant(id);
        return applyStatusResponse(response);
    };

    const retryProvisioning = async (id) => {
        const response = await tenantService.retryProvisioning(id);
        return applyStatusResponse(response);
    };

    const transferOwner = async (id, userId) => {
        const response = await tenantService.transferOwner(id, userId);
        currentTenant.value = response.data.data;
        return response.data.data;
    };

    const manageTenant = async (id) => {
        const response = await tenantService.manageTenant(id);
        return response.data.data;
    };

    const exitTenantContext = async () => {
        return tenantService.exitTenantContext();
    };

    // =========================================================
    // RETURN
    // =========================================================
    return {
        // State
        tenants,
        currentTenant,
        plans,
        pagination,
        filters,
        loading,
        error,

        // Getters
        totalTenants,
        hasTenants,
        isTenantLoading,

        // List
        fetchTenants,
        resetFilters,

        // Single
        fetchTenant,
        clearCurrentTenant,

        // Plans
        fetchPlans,

        // CRUD
        createTenant,
        updateTenant,

        // Lifecycle
        activateTenant,
        suspendTenant,
        archiveTenant,
        restoreTenant,
        retryProvisioning,
        transferOwner,

        // Manage context
        manageTenant,
        exitTenantContext
    };
});
