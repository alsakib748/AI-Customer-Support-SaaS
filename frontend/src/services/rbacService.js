import api from './api';

class RbacService {
    // =========================================================
    // ROLES
    // =========================================================
    listRoles(params = {}) {
        return api.get('/admin/rbac/roles', { params });
    }

    showRole(id) {
        return api.get(`/admin/rbac/roles/${id}`);
    }

    createRole(data) {
        return api.post('/admin/rbac/roles', data);
    }

    updateRole(id, data) {
        return api.put(`/admin/rbac/roles/${id}`, data);
    }

    deleteRole(id) {
        return api.delete(`/admin/rbac/roles/${id}`);
    }

    syncRolePermissions(roleId, permissions) {
        return api.put(`/admin/rbac/roles/${roleId}/permissions`, { permissions });
    }

    // =========================================================
    // PERMISSIONS
    // =========================================================
    listPermissions() {
        return api.get('/admin/rbac/permissions');
    }

    createPermission(data) {
        return api.post('/admin/rbac/permissions', data);
    }

    updatePermission(id, data) {
        return api.put(`/admin/rbac/permissions/${id}`, data);
    }

    deletePermission(id) {
        return api.delete(`/admin/rbac/permissions/${id}`);
    }

    // =========================================================
    // USER ↔ ROLE
    // =========================================================
    assignUserRole(userId, role) {
        return api.post(`/admin/rbac/users/${userId}/role`, { role });
    }

    revokeUserRole(userId) {
        return api.delete(`/admin/rbac/users/${userId}/role`);
    }
}

export default new RbacService();
