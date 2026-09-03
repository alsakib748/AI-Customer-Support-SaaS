import api from './api';

class CustomerService {
    /**
     * Get customers with filters
     */
    getCustomers(params = {}) {
        const cleanParams = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                cleanParams[key] = params[key];
            }
        });
        return api.get('/customers', { params: cleanParams });
    }

    /**
     * Get a single customer
     */
    getCustomer(id) {
        return api.get(`/customers/${id}`);
    }

    /**
     * Create a new customer
     */
    createCustomer(data) {
        return api.post('/customers', data);
    }

    /**
     * Update a customer
     */
    updateCustomer(id, data) {
        return api.put(`/customers/${id}`, data);
    }

    /**
     * Delete a customer (soft delete)
     */
    deleteCustomer(id) {
        return api.delete(`/customers/${id}`);
    }

    /**
     * Restore a deleted customer
     */
    restoreCustomer(id) {
        return api.post(`/customers/${id}/restore`);
    }

    /**
     * Block a customer
     */
    blockCustomer(id, reason = null) {
        return api.post(`/customers/${id}/block`, { reason });
    }

    /**
     * Unblock a customer
     */
    unblockCustomer(id) {
        return api.post(`/customers/${id}/unblock`);
    }

    /**
     * Add a tag to a customer
     */
    addTag(id, tag) {
        return api.post(`/customers/${id}/tags`, { tag });
    }

    /**
     * Remove a tag from a customer
     */
    removeTag(id, tag) {
        return api.delete(`/customers/${id}/tags/${encodeURIComponent(tag)}`);
    }

    /**
     * Bulk delete customers
     */
    bulkDelete(ids) {
        return api.post('/customers/bulk-delete', { ids });
    }

    /**
     * Get customer statistics
     */
    getStatistics() {
        return api.get('/customers/statistics');
    }

    /**
     * Get all tags
     */
    getTags() {
        return api.get('/customers/tags');
    }
}

export default new CustomerService();
