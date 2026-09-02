import api from './api';

class TeamService {
    // ============================================
    // MEMBERS
    // ============================================

    /**
     * Get all tenants (for Super Admin)
     */
    getTenants() {
        return api.get('/team/members/tenants');
    }

    /**
     * Get team members with filters
     */
    getMembers(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/team/members', { params: cleanParams });
    }

    getMember(id) {
        return api.get(`/team/members/${id}`);
    }

    updateMember(id, data) {
        return api.put(`/team/members/${id}`, data);
    }

    removeMember(id) {
        return api.delete(`/team/members/${id}`);
    }

    getStatistics() {
        return api.get('/team/members/statistics');
    }

    getDepartments() {
        return api.get('/team/members/departments');
    }

    // ============================================
    // INVITATIONS
    // ============================================

    /**
     * Get invitations
     */
    getInvitations(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/team/invitations', { params: cleanParams });
    }

    sendInvitation(data) {
        return api.post('/team/invitations', data);
    }

    resendInvitation(id) {
        return api.post(`/team/invitations/${id}/resend`);
    }

    revokeInvitation(id) {
        return api.delete(`/team/invitations/${id}`);
    }

    acceptInvitation(token, data) {
        return api.post(`/team/invitations/accpet/${token}`, data);
    }
}

export default new TeamService();
