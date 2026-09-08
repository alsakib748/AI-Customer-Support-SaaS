// src/services/ticketService.js

import api from './api';

class TicketService {
    /**
     * Get tickets with filters
     */
    getTickets(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/tickets', { params: cleanParams });
    }

    /**
     * Get a single ticket
     */
    getTicket(id) {
        return api.get(`/tickets/${id}`);
    }

    /**
     * Create a new ticket
     */
    createTicket(data) {
        return api.post('/tickets', data);
    }

    /**
     * Update a ticket
     */
    updateTicket(id, data) {
        return api.put(`/tickets/${id}`, data);
    }

    /**
     * Delete a ticket
     */
    deleteTicket(id) {
        return api.delete(`/tickets/${id}`);
    }

    /**
     * Assign a ticket
     */
    assignTicket(id, userId) {
        return api.post(`/tickets/${id}/assign`, { user_id: userId });
    }

    /**
     * Unassign a ticket
     */
    unassignTicket(id) {
        return api.post(`/tickets/${id}/unassign`);
    }

    /**
     * Start a ticket (set to in_progress)
     */
    startTicket(id) {
        return api.post(`/tickets/${id}/start`);
    }

    /**
     * Set ticket to pending
     */
    pendingTicket(id) {
        return api.post(`/tickets/${id}/pending`);
    }

    /**
     * Resolve a ticket
     */
    resolveTicket(id) {
        return api.post(`/tickets/${id}/resolve`);
    }

    /**
     * Reopen a ticket
     */
    reopenTicket(id) {
        return api.post(`/tickets/${id}/reopen`);
    }

    /**
     * Close a ticket
     */
    closeTicket(id) {
        return api.post(`/tickets/${id}/close`);
    }

    /**
     * Get ticket statistics
     */
    getStatistics() {
        return api.get('/tickets/statistics');
    }

    /**
     * Get ticket comments
     */
    getComments(ticketId, params = {}) {
        return api.get(`/tickets/${ticketId}/comments`, { params });
    }

    /**
     * Add a comment to a ticket
     */
    addComment(ticketId, data) {
        return api.post(`/tickets/${ticketId}/comments`, data);
    }

    /**
     * Delete a comment
     */
    deleteComment(ticketId, commentId) {
        return api.delete(`/tickets/${ticketId}/comments/${commentId}`);
    }
}

export default new TicketService();
