import api from './api';

class NotificationService {
    list(params = {}) {
        return api.get('/admin/notifications', { params });
    }

    unreadCount() {
        return api.get('/admin/notifications/unread-count', { skipErrorToast: true });
    }

    markAsRead(id) {
        return api.patch(`/admin/notifications/${id}/read`);
    }

    markAsUnread(id) {
        return api.patch(`/admin/notifications/${id}/unread`);
    }

    markAllAsRead(payload = {}) {
        return api.post('/admin/notifications/mark-all-read', payload);
    }

    remove(id) {
        return api.delete(`/admin/notifications/${id}`);
    }
}

export default new NotificationService();
