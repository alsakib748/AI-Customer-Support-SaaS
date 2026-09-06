// src/services/messageService.js

import api from './api';

class MessageService {
    /**
     * Get messages for a conversation
     */
    getMessages(conversationId, params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get(`/conversations/${conversationId}/messages`, { params: cleanParams });
    }

    /**
     * Send a message as agent
     */
    sendMessage(conversationId, data) {
        return api.post(`/conversations/${conversationId}/messages`, data);
    }

    /**
     * Add an internal note
     */
    addNote(conversationId, data) {
        return api.post(`/conversations/${conversationId}/notes`, data);
    }

    /**
     * Get a single message
     */
    getMessage(id) {
        return api.get(`/messages/${id}`);
    }

    /**
     * Delete a message
     */
    deleteMessage(id) {
        return api.delete(`/messages/${id}`);
    }
}

export default new MessageService();
