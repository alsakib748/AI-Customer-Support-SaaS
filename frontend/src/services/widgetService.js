// src/services/widgetService.js

import api from './api';

class WidgetService {
    /**
     * Get all widgets for current tenant
     */
    getWidgets(params = {}) {
        return api.get('/chat-widgets', { params });
    }

    /**
     * Get a single widget
     */
    getWidget(id) {
        return api.get(`/chat-widgets/${id}`);
    }

    /**
     * Create a new widget
     */
    createWidget(data) {
        return api.post('/chat-widgets', data);
    }

    /**
     * Update a widget
     */
    updateWidget(id, data) {
        return api.put(`/chat-widgets/${id}`, data);
    }

    /**
     * Delete a widget
     */
    deleteWidget(id) {
        return api.delete(`/chat-widgets/${id}`);
    }

    /**
     * Enable a widget
     */
    enableWidget(id) {
        return api.post(`/chat-widgets/${id}/enable`);
    }

    /**
     * Disable a widget
     */
    disableWidget(id) {
        return api.post(`/chat-widgets/${id}/disable`);
    }

    /**
     * Regenerate widget public key
     */
    regenerateKey(id) {
        return api.post(`/chat-widgets/${id}/regenerate-key`);
    }

    /**
     * Get installation code
     */
    getInstallationCode(id) {
        return api.get(`/chat-widgets/${id}/installation-code`);
    }
}

export default new WidgetService();
