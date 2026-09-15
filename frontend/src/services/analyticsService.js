// src/services/analyticsService.js

import api from './api';

class AnalyticsService {
    /**
     * Build query params from filters object.
     */
    buildParams(filters = {}) {
        const params = {};
        Object.keys(filters).forEach((key) => {
            const value = filters[key];
            if (value !== null && value !== undefined && value !== '') {
                params[key] = value;
            }
        });
        return params;
    }

    getOverview(filters = {}) {
        return api.get('/analytics/overview', { params: this.buildParams(filters) });
    }

    getConversations(filters = {}) {
        return api.get('/analytics/conversations', { params: this.buildParams(filters) });
    }

    getCustomers(filters = {}) {
        return api.get('/analytics/customers', { params: this.buildParams(filters) });
    }

    getAgents(filters = {}) {
        return api.get('/analytics/agents', { params: this.buildParams(filters) });
    }

    getTickets(filters = {}) {
        return api.get('/analytics/tickets', { params: this.buildParams(filters) });
    }

    getAI(filters = {}) {
        return api.get('/analytics/ai', { params: this.buildParams(filters) });
    }

    getWidget(filters = {}) {
        return api.get('/analytics/widget', { params: this.buildParams(filters) });
    }

    getKnowledgeBase(filters = {}) {
        return api.get('/analytics/knowledge-base', { params: this.buildParams(filters) });
    }

    requestExport(data) {
        return api.post('/analytics/exports', data);
    }

    getExports() {
        return api.get('/analytics/exports');
    }

    downloadExport(exportId) {
        return api.get(`/analytics/exports/${exportId}/download`, {
            responseType: 'blob',
        });
    }
}

export default new AnalyticsService();
