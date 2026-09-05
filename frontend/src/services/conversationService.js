// src/services/conversationService.js

import api from './api';

class ConversationService {
    /**
     * Get conversations with filters
     */
    getConversations(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/conversations', { params: cleanParams });
    }

    /**
     * Get a single conversation
     */
    getConversation(id) {
        return api.get(`/conversations/${id}`);
    }

    /**
     * Create a new conversation
     */
    createConversation(data) {
        return api.post('/conversations', data);
    }

    /**
     * Update a conversation
     */
    updateConversation(id, data) {
        return api.put(`/conversations/${id}`, data);
    }

    /**
     * Delete a conversation
     */
    deleteConversation(id) {
        return api.delete(`/conversations/${id}`);
    }

    /**
     * Resolve a conversation
     */
    resolveConversation(id) {
        return api.post(`/conversations/${id}/resolve`);
    }

    /**
     * Reopen a conversation
     */
    reopenConversation(id) {
        return api.post(`/conversations/${id}/reopen`);
    }

    /**
     * Close a conversation
     */
    closeConversation(id) {
        return api.post(`/conversations/${id}/close`);
    }

    /**
     * Assign a conversation
     */
    assignConversation(id, userId) {
        return api.post(`/conversations/${id}/assign`, { user_id: userId });
    }

    /**
     * Unassign a conversation
     */
    unassignConversation(id) {
        return api.post(`/conversations/${id}/unassign`);
    }

    /**
     * Get conversation statistics
     */
    getStatistics() {
        return api.get('/conversations/statistics');
    }
}

export default new ConversationService();
