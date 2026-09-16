// src/services/billingService.js

import api from './api';

class BillingService {
    // ============================================
    // PLANS
    // ============================================

    getPlans(params = {}) {
        return api.get('/plans', { params });
    }

    getPlan(id) {
        return api.get(`/plans/${id}`);
    }

    comparePlans(planIds) {
        return api.get('/plans/compare', { params: { plan_ids: planIds } });
    }

    // ============================================
    // SUBSCRIPTION
    // ============================================

    getCurrentSubscription() {
        return api.get('/subscription/current');
    }

    createSubscription(data) {
        return api.post('/subscription', data);
    }

    upgradeSubscription(planId) {
        return api.post('/subscription/upgrade', { plan_id: planId });
    }

    downgradeSubscription(planId) {
        return api.post('/subscription/downgrade', { plan_id: planId });
    }

    cancelSubscription(immediately = false) {
        return api.post('/subscription/cancel', { immediately });
    }

    resumeSubscription() {
        return api.post('/subscription/resume');
    }

    validateCoupon(code) {
        return api.post('/subscription/validate-coupon', { code });
    }

    // ============================================
    // INVOICES
    // ============================================

    getInvoices(params = {}) {
        return api.get('/invoices', { params });
    }

    getInvoice(id) {
        return api.get(`/invoices/${id}`);
    }

    getInvoiceStatistics() {
        return api.get('/invoices/statistics');
    }

    downloadInvoice(id) {
        return api.get(`/invoices/${id}/download`);
    }

    // ============================================
    // PAYMENTS
    // ============================================

    getPayments(params = {}) {
        return api.get('/payments', { params });
    }

    getPaymentStatistics() {
        return api.get('/payments/statistics');
    }
}

export default new BillingService();
