import api from './api';

class WorkspaceService {
    /**
     * Get current workspace
     */
    getWorkspace() {
        return api.get('/workspace');
    }

    /**
     * Update workspace
     */
    updateWorkspace(data) {
        return api.put('/workspace', data);
    }

    /**
     * Update workspace logo
     */
    updateLogo(file) {
        const formData = new FormData();
        formData.append('logo', file);

        return api.post('/workspace/logo', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
    }

    /**
     * Delete workspace logo
     */
    deleteLogo() {
        return api.delete('/workspace/logo');
    }

    /**
     * Update workspace favicon
     */
    updateFavicon(file) {
        const formData = new FormData();
        formData.append('favicon', file);

        return api.post('/workspace/favicon', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
    }

    /**
     * Delete workspace favicon
     */
    deleteFavicon() {
        return api.delete('/workspace/favicon');
    }

    /**
     * Update business hours
     */
    updateBusinessHours(businessHours) {
        return api.put('/workspace/business-hours', { business_hours: businessHours });
    }

    /**
     * Get workspace statistics
     */
    getStatistics() {
        return api.get('/workspace/statistics');
    }
}

export default new WorkspaceService();
