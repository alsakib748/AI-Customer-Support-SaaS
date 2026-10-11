// src/services/profileService.js

import api from './api';

class ProfileService {
    // ============================================
    // PROFILE (any authenticated role)
    // ============================================
    getProfile() {
        return api.get('/profile');
    }

    updateProfile(data) {
        return api.put('/profile', data);
    }

    updateAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);

        return api.post('/profile/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    }
}

export default new ProfileService();
