import api from './api';

class DemoRequestService {
    getDemoRequests(params = {}) {
        return api.get('/admin/demo-requests', { params });
    }

    getDemoRequest(id) {
        return api.get(`/admin/demo-requests/${id}`);
    }

    updateDemoRequest(id, payload) {
        return api.put(`/admin/demo-requests/${id}`, payload);
    }
}

export default new DemoRequestService();
