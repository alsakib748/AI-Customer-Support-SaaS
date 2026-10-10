// src/services/systemSettingsService.js

import api from './api';

class SystemSettingsService {
    // ============================================
    // HUB
    // ============================================
    getIndex() {
        return api.get('/admin/settings');
    }

    // ============================================
    // GROUP READ / WRITE
    // ============================================
    getGroup(group) {
        return api.get(`/admin/settings/${group}`);
    }

    updateGroup(group, data) {
        return api.put(`/admin/settings/${group}`, data);
    }

    // ============================================
    // EMAIL
    // ============================================
    testEmail(data = {}) {
        return api.post('/admin/settings/email/test', data);
    }

    // ============================================
    // INTEGRATIONS
    // ============================================
    getIntegrations() {
        return api.get('/admin/settings/integrations');
    }

    testIntegration(provider) {
        return api.post(`/admin/settings/integrations/${provider}/test`);
    }

    // ============================================
    // OPERATIONS
    // ============================================
    getHealth() {
        return api.get('/admin/settings/operations/health');
    }
}

export default new SystemSettingsService();
