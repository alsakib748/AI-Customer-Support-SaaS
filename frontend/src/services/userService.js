// src/services/userService.js

import api from './api';

class UserService {
    // ============================================
    // ADMIN - USERS LIST
    // ============================================
    getUsers(params = {}) {
        return api.get('/admin/users', { params });
    }

    getUserStats() {
        return api.get('/admin/users/stats');
    }

    createUser(data) {
        return api.post('/admin/users', data);
    }

    getUser(id) {
        return api.get(`/admin/users/${id}`);
    }

    updateUser(id, data) {
        return api.put(`/admin/users/${id}`, data);
    }

    // ============================================
    // ADMIN - USER DETAILS
    // ============================================
    getUserMemberships(id) {
        return api.get(`/admin/users/${id}/memberships`);
    }

    getUserActivity(id, limit = 50) {
        return api.get(`/admin/users/${id}/activity`, { params: { limit } });
    }

    // ============================================
    // ADMIN - LIFECYCLE ACTIONS
    // ============================================
    activateUser(id) {
        return api.post(`/admin/users/${id}/activate`);
    }

    suspendUser(id, reason = null) {
        return api.post(`/admin/users/${id}/suspend`, { reason });
    }

    revokeUserSessions(id, reason = null) {
        return api.post(`/admin/users/${id}/revoke-sessions`, { reason });
    }
}

export default new UserService();
