// src/services/tenantService.js

import api from './api';

class TenantService {
    // ============================================
    // ADMIN - TENANTS LIST
    // ============================================
    getTenants(params = {}) {
        return api.get('/admin/tenants', { params });
    }

    createTenant(data) {
        return api.post('/admin/tenants', data);
    }

    getTenant(id) {
        return api.get(`/admin/tenants/${id}`);
    }

    updateTenant(id, data) {
        return api.put(`/admin/tenants/${id}`, data);
    }

    getTenantPlans() {
        return api.get('/admin/tenants/plans');
    }

    // ============================================
    // ADMIN - TENANT DETAILS
    // ============================================
    getTenantMembers(id) {
        return api.get(`/admin/tenants/${id}/members`);
    }

    getTenantUsage(id) {
        return api.get(`/admin/tenants/${id}/usage`);
    }

    getTenantActivity(id, limit = 50) {
        return api.get(`/admin/tenants/${id}/activity`, { params: { limit } });
    }

    // ============================================
    // ADMIN - LIFECYCLE ACTIONS
    // ============================================
    activateTenant(id) {
        return api.post(`/admin/tenants/${id}/activate`);
    }

    suspendTenant(id, reason = null) {
        return api.post(`/admin/tenants/${id}/suspend`, { reason });
    }

    archiveTenant(id, confirmation) {
        return api.post(`/admin/tenants/${id}/archive`, { confirmation });
    }

    restoreTenant(id) {
        return api.post(`/admin/tenants/${id}/restore`);
    }

    retryProvisioning(id) {
        return api.post(`/admin/tenants/${id}/retry-provisioning`);
    }

    transferOwner(id, userId) {
        return api.post(`/admin/tenants/${id}/transfer-owner`, { user_id: userId });
    }

    // ============================================
    // ADMIN - MANAGE TENANT CONTEXT
    // ============================================
    manageTenant(id) {
        return api.post(`/admin/tenants/${id}/manage`);
    }

    exitTenantContext() {
        return api.post('/admin/tenants/exit-context');
    }
}

export default new TenantService();
