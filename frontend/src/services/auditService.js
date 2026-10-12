import api from './api';

class AuditService {
    getLogs(params = {}) {
        return api.get('/admin/audit-logs', { params });
    }

    getStatistics(params = {}) {
        return api.get('/admin/audit-logs/statistics', { params });
    }
}

export default new AuditService();
