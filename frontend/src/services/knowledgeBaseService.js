// src/services/knowledgeBaseService.js

import api from './api';

class KnowledgeBaseService {
    // ============================================
    // CATEGORIES
    // ============================================

    getCategories(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/knowledge-base/categories', { params: cleanParams });
    }

    getAllCategories() {
        return api.get('/knowledge-base/categories/all');
    }

    getCategory(id) {
        return api.get(`/knowledge-base/categories/${id}`);
    }

    createCategory(data) {
        return api.post('/knowledge-base/categories', data);
    }

    updateCategory(id, data) {
        return api.put(`/knowledge-base/categories/${id}`, data);
    }

    deleteCategory(id) {
        return api.delete(`/knowledge-base/categories/${id}`);
    }

    activateCategory(id) {
        return api.post(`/knowledge-base/categories/${id}/activate`);
    }

    deactivateCategory(id) {
        return api.post(`/knowledge-base/categories/${id}/deactivate`);
    }

    // ============================================
    // ARTICLES
    // ============================================

    getArticles(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/knowledge-base/articles', { params: cleanParams });
    }

    getArticle(id) {
        return api.get(`/knowledge-base/articles/${id}`);
    }

    createArticle(data) {
        return api.post('/knowledge-base/articles', data);
    }

    updateArticle(id, data) {
        return api.put(`/knowledge-base/articles/${id}`, data);
    }

    deleteArticle(id) {
        return api.delete(`/knowledge-base/articles/${id}`);
    }

    publishArticle(id) {
        return api.post(`/knowledge-base/articles/${id}/publish`);
    }

    unpublishArticle(id) {
        return api.post(`/knowledge-base/articles/${id}/unpublish`);
    }

    archiveArticle(id) {
        return api.post(`/knowledge-base/articles/${id}/archive`);
    }

    getStatistics() {
        return api.get('/knowledge-base/articles/statistics');
    }

    searchForAI(query) {
        return api.get('/knowledge-base/articles/search/ai', { params: { query } });
    }
}

export default new KnowledgeBaseService();
