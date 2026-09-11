// src/services/aiService.js

import api from './api';

class AIService {
    /**
     * Get AI configuration
     */
    getConfiguration() {
        return api.get('/ai/configuration');
    }

    /**
     * Update AI configuration
     */
    updateConfiguration(data) {
        return api.put('/ai/configuration', data);
    }

    /**
     * Test AI
     */
    testAI(message) {
        return api.post('/ai/test', { message });
    }

    /**
     * Stream AI response (using EventSource)
     */
    streamAIResponse(conversationId, messageId) {
        const token = localStorage.getItem('auth_token');
        const tenantId = localStorage.getItem('current_tenant_id');

        return new EventSource(`/api/v1/ai/stream/${conversationId}/${messageId}?` + `token=${token}&tenant_id=${tenantId}`);
    }

    /**
     * Get AI usage statistics
     */
    getUsage(params = {}) {
        return api.get('/ai/usage', { params });
    }

    /**
     * Get AI analytics
     */
    getAnalytics(params = {}) {
        return api.get('/ai/analytics', { params });
    }

    /**
     * Get AI health metrics
     */
    getHealth() {
        return api.get('/ai/health');
    }

    /**
     * Get AI logs
     */
    getLogs(params = {}) {
        return api.get('/ai/logs', { params });
    }
}

export default new AIService();
