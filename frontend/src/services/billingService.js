// // src/services/billingService.js

// import api from './api';

// class BillingService {
//     // ============================================
//     // PLANS
//     // ============================================

//     // ============================================
//     // USAGE
//     // ============================================
//     getUsage() {
//         return api.get('/billing/usage');
//     }

//     // ============================================
//     // CHECKOUT
//     // ============================================
//     checkout(data) {
//         return api.post('/subscription/checkout', data);
//     }

//     // ============================================
//     // ADMIN - PLANS
//     // ============================================
//     getAdminPlans(params = {}) {
//         return api.get('/admin/billing/plans', { params });
//     }

//     createAdminPlan(data) {
//         return api.post('/admin/billing/plans', data);
//     }

//     updateAdminPlan(id, data) {
//         return api.put(`/admin/billing/plans/${id}`, data);
//     }

//     deleteAdminPlan(id) {
//         return api.delete(`/admin/billing/plans/${id}`);
//     }

//     // ============================================
//     // ADMIN - SUBSCRIPTIONS / INVOICES / PAYMENTS
//     // ============================================
//     getAdminSubscriptions(params = {}) {
//         return api.get('/admin/billing/subscriptions', { params });
//     }

//     getAdminInvoices(params = {}) {
//         return api.get('/admin/billing/invoices', { params });
//     }

//     getAdminPayments(params = {}) {
//         return api.get('/admin/billing/payments', { params });
//     }

//     // ============================================
//     // ADMIN - COUPONS
//     // ============================================
//     getAdminCoupons(params = {}) {
//         return api.get('/admin/billing/coupons', { params });
//     }

//     createAdminCoupon(data) {
//         return api.post('/admin/billing/coupons', data);
//     }

//     updateAdminCoupon(id, data) {
//         return api.put(`/admin/billing/coupons/${id}`, data);
//     }

//     deleteAdminCoupon(id) {
//         return api.delete(`/admin/billing/coupons/${id}`);
//     }

//     // ============================================
//     // ADMIN - ANALYTICS
//     // ============================================
//     getAdminAnalytics() {
//         return api.get('/admin/billing/analytics');
//     }

//     getAdminTenantSummary(tenantId) {
//         return api.get(`/admin/billing/tenants/${tenantId}`);
//     }

//     getPlans(params = {}) {
//         return api.get('/plans', { params });
//     }

//     getPlan(id) {
//         return api.get(`/plans/${id}`);
//     }

//     comparePlans(planIds) {
//         return api.get('/plans/compare', { params: { plan_ids: planIds } });
//     }

//     // ============================================
//     // SUBSCRIPTION
//     // ============================================

//     getCurrentSubscription() {
//         return api.get('/subscription/current');
//     }

//     createSubscription(data) {
//         return api.post('/subscription', data);
//     }

//     upgradeSubscription(planId) {
//         return api.post('/subscription/upgrade', { plan_id: planId });
//     }

//     downgradeSubscription(planId) {
//         return api.post('/subscription/downgrade', { plan_id: planId });
//     }

//     cancelSubscription(immediately = false) {
//         return api.post('/subscription/cancel', { immediately });
//     }

//     resumeSubscription() {
//         return api.post('/subscription/resume');
//     }

//     validateCoupon(code) {
//         return api.post('/subscription/validate-coupon', { code });
//     }

//     // ============================================
//     // INVOICES
//     // ============================================

//     getInvoices(params = {}) {
//         return api.get('/invoices', { params });
//     }

//     getInvoice(id) {
//         return api.get(`/invoices/${id}`);
//     }

//     getInvoiceStatistics() {
//         return api.get('/invoices/statistics');
//     }

//     downloadInvoice(id) {
//         return api.get(`/invoices/${id}/download`);
//     }

//     // ============================================
//     // PAYMENTS
//     // ============================================

//     getPayments(params = {}) {
//         return api.get('/payments', { params });
//     }

//     getPaymentStatistics() {
//         return api.get('/payments/statistics');
//     }
// }

// export default new BillingService();

// src/services/billingService.js

import api from './api';

class BillingService {
    // ============================================
    // PLANS (Public / Tenant)
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

    upgradeSubscription(planId, cycle = null) {
        return api.post('/subscription/upgrade', {
            plan_id: planId,
            billing_cycle: cycle
        });
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

    checkout(data) {
        return api.post('/subscription/checkout', data);
    }

    // ============================================
    // USAGE
    // ============================================
    getUsage() {
        return api.get('/billing/usage');
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

    // ============================================
    // ADMIN - PLANS
    // ============================================
    getAdminPlans(params = {}) {
        return api.get('/admin/billing/plans', { params });
    }

    createAdminPlan(data) {
        return api.post('/admin/billing/plans', data);
    }

    updateAdminPlan(id, data) {
        return api.put(`/admin/billing/plans/${id}`, data);
    }

    deleteAdminPlan(id) {
        return api.delete(`/admin/billing/plans/${id}`);
    }

    // ============================================
    // ADMIN - SUBSCRIPTIONS / INVOICES / PAYMENTS
    // ============================================
    getAdminSubscriptions(params = {}) {
        return api.get('/admin/billing/subscriptions', { params });
    }

    getAdminInvoices(params = {}) {
        return api.get('/admin/billing/invoices', { params });
    }

    getAdminPayments(params = {}) {
        return api.get('/admin/billing/payments', { params });
    }

    // ============================================
    // ADMIN - COUPONS
    // ============================================
    getAdminCoupons(params = {}) {
        return api.get('/admin/billing/coupons', { params });
    }

    createAdminCoupon(data) {
        return api.post('/admin/billing/coupons', data);
    }

    updateAdminCoupon(id, data) {
        return api.put(`/admin/billing/coupons/${id}`, data);
    }

    deleteAdminCoupon(id) {
        return api.delete(`/admin/billing/coupons/${id}`);
    }

    // ============================================
    // ADMIN - ANALYTICS
    // ============================================
    getAdminAnalytics() {
        return api.get('/admin/billing/analytics');
    }

    getAdminTenantSummary(tenantId) {
        return api.get(`/admin/billing/tenants/${tenantId}`);
    }

    getProviders() {
        return api.get('/subscription/providers');
    }

    // Admin refund
    refundPayment(paymentId, data) {
        return api.post(`/admin/billing/payments/${paymentId}/refund`, data);
    }

    // Admin plan provider prices
    getPlanProviderPrices(planId) {
        return api.get(`/admin/billing/plans/${planId}/prices`);
    }

    createPlanProviderPrice(planId, data) {
        return api.post(`/admin/billing/plans/${planId}/prices`, data);
    }

    deletePlanProviderPrice(priceId) {
        return api.delete(`/admin/billing/prices/${priceId}`);
    }
}

export default new BillingService();
