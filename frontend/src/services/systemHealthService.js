import api from './api';

class SystemHealthService {
    getSnapshot(params = {}) {
        return api.get('/admin/system-health', { params });
    }

    getChecks() {
        return api.get('/admin/system-health/checks');
    }

    getCheck(key) {
        return api.get(`/admin/system-health/checks/${encodeURIComponent(key)}`);
    }

    runCheck(key) {
        return api.post(`/admin/system-health/checks/${encodeURIComponent(key)}/run`);
    }

    getMetrics(params = {}) {
        return api.get('/admin/system-health/metrics', { params });
    }

    getIncidents(params = {}) {
        return api.get('/admin/system-health/incidents', { params });
    }

    getIncident(id) {
        return api.get(`/admin/system-health/incidents/${id}`);
    }

    acknowledgeIncident(id) {
        return api.post(`/admin/system-health/incidents/${id}/acknowledge`);
    }

    resolveIncident(id) {
        return api.post(`/admin/system-health/incidents/${id}/resolve`);
    }

    getEvents(params = {}) {
        return api.get('/admin/system-health/events', { params });
    }
}

export default new SystemHealthService();
