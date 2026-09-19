// // src/stores/billing.js

// import { defineStore } from 'pinia';
// import { ref, computed, reactive } from 'vue';
// import billingService from '@/services/billingService';
// import { toast } from 'vue3-toastify';

// export const useBillingStore = defineStore('billing', () => {
//     // ============================================
//     // STATE
//     // ============================================

//     // Independent usage fetch (from /billing/usage endpoint)
//     const usageSummary = ref(null);

//     // Split pagination (fixes race between invoices and payments)
//     const invoicesPagination = ref(null);
//     const paymentsPagination = ref(null);

//     // ============================================
//     // ADMIN STATE
//     // ============================================
//     const adminPlans = ref([]);
//     const adminSubscriptions = ref([]);
//     const adminInvoices = ref([]);
//     const adminPayments = ref([]);
//     const adminCoupons = ref([]);
//     const analytics = ref(null);
//     const adminPagination = ref({
//         plans: null,
//         subscriptions: null,
//         invoices: null,
//         payments: null,
//         coupons: null
//     });

//     const plans = ref([]);
//     const currentPlan = ref(null);
//     const subscription = ref(null);

//     const invoices = ref([]);
//     const payments = ref([]);
//     const invoiceStats = ref({
//         total: 0,
//         paid: 0,
//         open: 0,
//         overdue: 0,
//         total_amount: 0,
//         outstanding_amount: 0
//     });
//     const paymentStats = ref({
//         total: 0,
//         completed: 0,
//         failed: 0,
//         refunded: 0,
//         total_revenue: 0,
//         total_refunded: 0,
//         by_provider: []
//     });
//     const pagination = ref(null);
//     const loading = ref(false);
//     const saving = ref(false);
//     const errors = ref({});

//     const filters = reactive({
//         status: null,
//         date_from: null,
//         date_to: null,
//         per_page: 20
//     });

//     // ============================================
//     // GETTERS
//     // ============================================

//     const hasSubscription = computed(() => !!subscription.value);
//     const isActive = computed(() => {
//         const s = subscription.value;
//         if (!s) return false;
//         if (s.is_active !== undefined) return s.is_active;
//         return ['active', 'trialing'].includes(s.status);
//     });
//     const isTrialing = computed(() => {
//         const s = subscription.value;
//         if (!s) return false;
//         if (s.is_trialing !== undefined) return s.is_trialing;
//         return s.status === 'trialing';
//     });
//     const isCancelled = computed(() => {
//         const s = subscription.value;
//         if (!s) return false;
//         if (s.is_cancelled !== undefined) return s.is_cancelled;
//         return s.status === 'cancelled';
//     });

//     const isExpired = computed(() => {
//         const s = subscription.value;
//         if (!s) return false;
//         if (s.is_expired !== undefined) return s.is_expired;
//         return s.status === 'expired' || (s.ends_at && new Date(s.ends_at) < new Date() && s.status !== 'active');
//     });
//     const planName = computed(() => subscription.value?.plan?.name ?? 'No Plan');
//     const daysRemaining = computed(() => subscription.value?.days_remaining ?? 0);
//     const trialDaysRemaining = computed(() => subscription.value?.trial_days_remaining ?? 0);
//     const usage = computed(() => subscription.value?.usage ?? {});
//     const activePlans = computed(() => plans.value.filter((p) => p.is_active !== false));
//     const aiUsagePercentage = computed(() => usage.value?.ai?.percentage ?? 0);
//     const agentsUsagePercentage = computed(() => usage.value?.agents?.percentage ?? 0);
//     const documentsUsagePercentage = computed(() => usage.value?.documents?.percentage ?? 0);
//     const storageUsagePercentage = computed(() => usage.value?.storage?.percentage ?? 0);

//     const isAiLimitReached = computed(() => aiUsagePercentage.value >= 100);
//     const isAgentsLimitReached = computed(() => agentsUsagePercentage.value >= 100);
//     const isDocumentsLimitReached = computed(() => documentsUsagePercentage.value >= 100);

//     // ============================================
//     // ACTIONS - USAGE
//     // ============================================

//     const fetchUsage = async () => {
//         loading.value = true;
//         try {
//             const response = await billingService.getUsage();
//             if (response.data.success) {
//                 usageSummary.value = response.data.data;
//                 return usageSummary.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch usage:', error);
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - CHECKOUT
//     // ============================================

//     const createCheckout = async (planId, billingCycle = 'monthly', gateway = null) => {
//         saving.value = true;
//         errors.value = {};
//         try {
//             const response = await billingService.checkout({
//                 plan_id: planId,
//                 billing_cycle: billingCycle,
//                 gateway
//             });
//             if (response.data.success) {
//                 return response.data.data; // { subscription_id, checkout_url, gateway }
//             }
//         } catch (error) {
//             console.error('Failed to create checkout:', error);
//             if (error.response?.data?.errors) {
//                 errors.value = error.response.data.errors;
//             }
//             toast.error(error.response?.data?.message || 'Failed to start checkout');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const confirmCheckout = async (sessionId) => {
//         saving.value = true;
//         try {
//             // Re-fetch subscription after gateway redirect
//             await fetchSubscription();
//             return subscription.value;
//         } finally {
//             saving.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - PLANS
//     // ============================================

//     const fetchAdminPlans = async (params = {}) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminPlans(params);
//             if (response.data.success !== false) {
//                 adminPlans.value = response.data.data || [];
//                 adminPagination.value.plans = response.data.meta || null;
//                 return adminPlans.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch admin plans:', error);
//             toast.error('Failed to load plans');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const createAdminPlan = async (payload) => {
//         saving.value = true;
//         errors.value = {};
//         try {
//             const response = await billingService.createAdminPlan(payload);
//             toast.success('Plan created');
//             return response.data.data;
//         } catch (error) {
//             if (error.response?.data?.errors) errors.value = error.response.data.errors;
//             toast.error(error.response?.data?.message || 'Failed to create plan');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const updateAdminPlan = async (id, payload) => {
//         saving.value = true;
//         errors.value = {};
//         try {
//             const response = await billingService.updateAdminPlan(id, payload);
//             toast.success('Plan updated');
//             return response.data.data;
//         } catch (error) {
//             if (error.response?.data?.errors) errors.value = error.response.data.errors;
//             toast.error(error.response?.data?.message || 'Failed to update plan');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const deleteAdminPlan = async (id) => {
//         saving.value = true;
//         try {
//             await billingService.deleteAdminPlan(id);
//             adminPlans.value = adminPlans.value.filter((p) => p.id !== id);
//             toast.success('Plan deleted');
//         } catch (error) {
//             toast.error(error.response?.data?.message || 'Failed to delete plan');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - ADMIN SUBSCRIPTIONS / INVOICES / PAYMENTS
//     // ============================================

//     const fetchAdminSubscriptions = async (params = {}) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminSubscriptions(params);
//             if (response.data.success !== false) {
//                 adminSubscriptions.value = response.data.data || [];
//                 adminPagination.value.subscriptions = response.data.meta || null;
//                 return adminSubscriptions.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch admin subscriptions:', error);
//             toast.error('Failed to load subscriptions');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchAdminInvoices = async (params = {}) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminInvoices(params);
//             if (response.data.success !== false) {
//                 adminInvoices.value = response.data.data || [];
//                 adminPagination.value.invoices = response.data.meta || null;
//                 return adminInvoices.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch admin invoices:', error);
//             toast.error('Failed to load invoices');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchAdminPayments = async (params = {}) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminPayments(params);
//             if (response.data.success !== false) {
//                 adminPayments.value = response.data.data || [];
//                 adminPagination.value.payments = response.data.meta || null;
//                 return adminPayments.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch admin payments:', error);
//             toast.error('Failed to load payments');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - ADMIN COUPONS
//     // ============================================

//     const fetchAdminCoupons = async (params = {}) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminCoupons(params);
//             if (response.data.success !== false) {
//                 adminCoupons.value = response.data.data || [];
//                 adminPagination.value.coupons = response.data.meta || null;
//                 return adminCoupons.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch admin coupons:', error);
//             toast.error('Failed to load coupons');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const createAdminCoupon = async (payload) => {
//         saving.value = true;
//         errors.value = {};
//         try {
//             const response = await billingService.createAdminCoupon(payload);
//             toast.success('Coupon created');
//             return response.data.data;
//         } catch (error) {
//             if (error.response?.data?.errors) errors.value = error.response.data.errors;
//             toast.error(error.response?.data?.message || 'Failed to create coupon');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const updateAdminCoupon = async (id, payload) => {
//         saving.value = true;
//         errors.value = {};
//         try {
//             const response = await billingService.updateAdminCoupon(id, payload);
//             toast.success('Coupon updated');
//             return response.data.data;
//         } catch (error) {
//             if (error.response?.data?.errors) errors.value = error.response.data.errors;
//             toast.error(error.response?.data?.message || 'Failed to update coupon');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const deleteAdminCoupon = async (id) => {
//         saving.value = true;
//         try {
//             await billingService.deleteAdminCoupon(id);
//             adminCoupons.value = adminCoupons.value.filter((c) => c.id !== id);
//             toast.success('Coupon deleted');
//         } catch (error) {
//             toast.error(error.response?.data?.message || 'Failed to delete coupon');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - ADMIN ANALYTICS
//     // ============================================

//     const fetchAdminAnalytics = async () => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminAnalytics();
//             if (response.data.success) {
//                 analytics.value = response.data.data;
//                 return analytics.value;
//             }
//         } catch (error) {
//             console.error('Failed to load analytics:', error);
//             toast.error('Failed to load analytics');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchAdminTenantSummary = async (tenantId) => {
//         loading.value = true;
//         try {
//             const response = await billingService.getAdminTenantSummary(tenantId);
//             return response.data.data;
//         } catch (error) {
//             console.error('Failed to load tenant summary:', error);
//             toast.error('Failed to load tenant summary');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchPlans = async (params = {}) => {
//         loading.value = true;

//         try {
//             const response = await billingService.getPlans(params);

//             if (response.data.success) {
//                 plans.value = response.data.data || [];
//                 return plans.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch plans:', error);
//             toast.error('Failed to load plans');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     // ============================================
//     // ACTIONS - SUBSCRIPTION
//     // ============================================

//     const fetchSubscription = async () => {
//         loading.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.getCurrentSubscription();

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 currentPlan.value = response.data.data?.plan ?? null;
//                 return subscription.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch subscription:', error);

//             if (error.response?.status === 404) {
//                 subscription.value = null;
//                 currentPlan.value = null;
//                 return null;
//             } else {
//                 toast.error('Failed to load subscription');
//             }

//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const createSubscription = async (data) => {
//         saving.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.createSubscription(data);

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 currentPlan.value = response.data.data?.plan;
//                 toast.success('Subscription created successfully! 🎉');
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to create subscription:', error);

//             if (error.response?.data?.errors) {
//                 errors.value = error.response.data.errors;
//             } else if (error.response?.data?.message) {
//                 errors.value = { general: [error.response.data.message] };
//             }

//             toast.error(errors.value.general?.[0] || 'Failed to create subscription');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const upgradeSubscription = async (planId) => {
//         saving.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.upgradeSubscription(planId);

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 currentPlan.value = response.data.data?.plan;
//                 toast.success('Subscription upgraded successfully! 🚀');
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to upgrade subscription:', error);
//             toast.error(error.response?.data?.message || 'Failed to upgrade subscription');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const downgradeSubscription = async (planId) => {
//         saving.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.downgradeSubscription(planId);

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 currentPlan.value = response.data.data?.plan;
//                 toast.success('Subscription downgrade scheduled! 📅');
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to downgrade subscription:', error);
//             toast.error(error.response?.data?.message || 'Failed to downgrade subscription');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const cancelSubscription = async (immediately = false) => {
//         saving.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.cancelSubscription(immediately);

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 toast.success(response.data.message || 'Subscription cancelled');
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to cancel subscription:', error);
//             toast.error(error.response?.data?.message || 'Failed to cancel subscription');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const resumeSubscription = async () => {
//         saving.value = true;
//         errors.value = {};

//         try {
//             const response = await billingService.resumeSubscription();

//             if (response.data.success) {
//                 subscription.value = response.data.data;
//                 toast.success('Subscription resumed successfully! ✅');
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to resume subscription:', error);
//             toast.error(error.response?.data?.message || 'Failed to resume subscription');
//             throw error;
//         } finally {
//             saving.value = false;
//         }
//     };

//     const validateCoupon = async (code) => {
//         try {
//             const response = await billingService.validateCoupon(code);

//             return {
//                 valid: response.data.success,
//                 message: response.data.message,
//                 data: response.data.data
//             };
//         } catch (error) {
//             console.error('Failed to validate coupon:', error);
//             return {
//                 valid: false,
//                 message: error.response?.data?.message || 'Invalid coupon'
//             };
//         }
//     };

//     // ============================================
//     // ACTIONS - INVOICES
//     // ============================================

//     const fetchInvoices = async (params = {}) => {
//         loading.value = true;

//         try {
//             const mergedParams = { ...filters.value, ...params };
//             const response = await billingService.getInvoices(mergedParams);

//             if (response.data.success) {
//                 invoices.value = response.data.data || [];
//                 invoicesPagination.value = response.data.meta || null;
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to fetch invoices:', error);
//             toast.error('Failed to load invoices');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchInvoiceStatistics = async () => {
//         try {
//             const response = await billingService.getInvoiceStatistics();

//             if (response.data.success) {
//                 invoiceStats.value = response.data.data;
//                 return invoiceStats.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch invoice statistics:', error);
//             return null;
//         }
//     };

//     // ============================================
//     // ACTIONS - PAYMENTS
//     // ============================================

//     const fetchPayments = async (params = {}) => {
//         loading.value = true;

//         try {
//             const response = await billingService.getPayments(params);

//             if (response.data.success) {
//                 payments.value = response.data.data || [];
//                 paymentsPagination.value = response.data.meta || null;
//                 return response.data;
//             }
//         } catch (error) {
//             console.error('Failed to fetch payments:', error);
//             toast.error('Failed to load payments');
//             throw error;
//         } finally {
//             loading.value = false;
//         }
//     };

//     const fetchPaymentStatistics = async () => {
//         try {
//             const response = await billingService.getPaymentStatistics();

//             if (response.data.success) {
//                 paymentStats.value = response.data.data;
//                 return paymentStats.value;
//             }
//         } catch (error) {
//             console.error('Failed to fetch payment statistics:', error);
//             return null;
//         }
//     };

//     // ============================================
//     // UTILITY
//     // ============================================

//     const resetFilters = () => {
//         Object.assign(filters, {
//             status: null,
//             date_from: null,
//             date_to: null,
//             per_page: 20
//         });
//     };

//     const getFieldError = (field) => {
//         if (errors.value && errors.value[field]) {
//             return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
//         }
//         return null;
//     };

//     const resetStore = () => {
//         plans.value = [];
//         currentPlan.value = null;
//         subscription.value = null;
//         invoices.value = [];
//         payments.value = [];
//         usageSummary.value = null;
//         invoicesPagination.value = null;
//         paymentsPagination.value = null;
//         adminPlans.value = [];
//         adminSubscriptions.value = [];
//         adminInvoices.value = [];
//         adminPayments.value = [];
//         adminCoupons.value = [];
//         analytics.value = null;
//         errors.value = {};
//         resetFilters();
//     };

//     // ============================================
//     // RETURN
//     // ============================================

//     return {
//         // State

//         plans,
//         currentPlan,
//         subscription,
//         invoices,
//         payments,
//         invoiceStats,
//         paymentStats,
//         pagination,
//         invoicesPagination,
//         paymentsPagination,
//         loading,
//         saving,
//         errors,
//         filters,
//         usageSummary,

//         // Admin State
//         adminPlans,
//         adminSubscriptions,
//         adminInvoices,
//         adminPayments,
//         adminCoupons,
//         analytics,
//         adminPagination,

//         // Getters

//         hasSubscription,
//         isActive,
//         isTrialing,
//         isCancelled,
//         isExpired,
//         planName,
//         daysRemaining,
//         trialDaysRemaining,
//         usage,
//         activePlans,
//         aiUsagePercentage,
//         agentsUsagePercentage,
//         documentsUsagePercentage,
//         storageUsagePercentage,
//         isAiLimitReached,
//         isAgentsLimitReached,
//         isDocumentsLimitReached,

//         // Plan Actions
//         fetchPlans,

//         // Subscription Actions
//         fetchSubscription,
//         createSubscription,
//         upgradeSubscription,
//         downgradeSubscription,
//         cancelSubscription,
//         resumeSubscription,
//         validateCoupon,

//         // ============================================
//         // CHECKOUT ACTIONS
//         // ============================================
//         createCheckout,
//         confirmCheckout,

//         // ============================================
//         // USAGE ACTIONS
//         // ============================================
//         fetchUsage,

//         // ============================================
//         // INVOICE ACTIONS
//         // ============================================
//         fetchInvoices,
//         fetchInvoiceStatistics,

//         // ============================================
//         // PAYMENT ACTIONS
//         // ============================================
//         fetchPayments,
//         fetchPaymentStatistics,

//         // ============================================
//         // ADMIN - PLANS
//         // ============================================
//         fetchAdminPlans,
//         createAdminPlan,
//         updateAdminPlan,
//         deleteAdminPlan,

//         // ============================================
//         // ADMIN - SUBSCRIPTIONS / INVOICES / PAYMENTS
//         // ============================================
//         fetchAdminSubscriptions,
//         fetchAdminInvoices,
//         fetchAdminPayments,

//         // ============================================
//         // ADMIN - COUPONS
//         // ============================================
//         fetchAdminCoupons,
//         createAdminCoupon,
//         updateAdminCoupon,
//         deleteAdminCoupon,

//         // ============================================
//         // ADMIN - ANALYTICS
//         // ============================================
//         fetchAdminAnalytics,
//         fetchAdminTenantSummary,

//         // ============================================
//         // UTILITY
//         // ============================================
//         resetFilters,
//         getFieldError,
//         resetStore
//     };
// });

// src/stores/billing.js

import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import billingService from '@/services/billingService';
import { toast } from 'vue3-toastify';

export const useBillingStore = defineStore('billing', () => {
    // ============================================
    // TENANT STATE
    // ============================================
    const plans = ref([]);
    const currentPlan = ref(null);
    const subscription = ref(null);
    const usageSummary = ref(null);

    const invoices = ref([]);
    const payments = ref([]);

    const invoiceStats = ref({
        total: 0,
        paid: 0,
        open: 0,
        overdue: 0,
        total_amount: 0,
        outstanding_amount: 0
    });

    const paymentStats = ref({
        total: 0,
        completed: 0,
        failed: 0,
        refunded: 0,
        total_revenue: 0,
        total_refunded: 0,
        by_provider: []
    });

    const invoicesPagination = ref(null);
    const paymentsPagination = ref(null);

    // ============================================
    // ADMIN STATE
    // ============================================
    const adminPlans = ref([]);
    const adminSubscriptions = ref([]);
    const adminInvoices = ref([]);
    const adminPayments = ref([]);
    const adminCoupons = ref([]);
    const analytics = ref(null);

    const adminPagination = reactive({
        plans: null,
        subscriptions: null,
        invoices: null,
        payments: null,
        coupons: null
    });

    // ============================================
    // UI STATE
    // ============================================
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = reactive({
        status: null,
        date_from: null,
        date_to: null,
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================
    const hasSubscription = computed(() => !!subscription.value);

    const isActive = computed(() => {
        const s = subscription.value;
        if (!s) return false;
        if (s.is_active !== undefined) return s.is_active;
        return ['active', 'trialing'].includes(s.status);
    });

    const isTrialing = computed(() => {
        const s = subscription.value;
        if (!s) return false;
        if (s.is_trialing !== undefined) return s.is_trialing;
        return s.status === 'trialing';
    });

    const isCancelled = computed(() => {
        const s = subscription.value;
        if (!s) return false;
        if (s.is_cancelled !== undefined) return s.is_cancelled;
        return s.status === 'cancelled';
    });

    const isExpired = computed(() => {
        const s = subscription.value;
        if (!s) return false;
        if (s.is_expired !== undefined) return s.is_expired;
        return s.status === 'expired' || (s.ends_at && new Date(s.ends_at) < new Date() && s.status !== 'active');
    });

    const planName = computed(() => subscription.value?.plan?.name ?? 'No Plan');
    const daysRemaining = computed(() => subscription.value?.days_remaining ?? 0);
    const trialDaysRemaining = computed(() => subscription.value?.trial_days_remaining ?? 0);

    const usage = computed(() => usageSummary.value ?? subscription.value?.usage ?? {});

    const activePlans = computed(() => plans.value.filter((p) => p.is_active !== false));

    // Usage percentages
    const aiUsagePercentage = computed(() => usage.value?.ai?.percentage ?? 0);
    const agentsUsagePercentage = computed(() => usage.value?.agents?.percentage ?? 0);
    const customersUsagePercentage = computed(() => usage.value?.customers?.percentage ?? 0);
    const widgetsUsagePercentage = computed(() => usage.value?.widgets?.percentage ?? 0);
    const documentsUsagePercentage = computed(() => usage.value?.documents?.percentage ?? 0);
    const storageUsagePercentage = computed(() => usage.value?.storage?.percentage ?? 0);
    const conversationsUsagePercentage = computed(() => usage.value?.conversations?.percentage ?? 0);

    const isAiLimitReached = computed(() => aiUsagePercentage.value >= 100);
    const isAgentsLimitReached = computed(() => agentsUsagePercentage.value >= 100);
    const isDocumentsLimitReached = computed(() => documentsUsagePercentage.value >= 100);
    const isStorageLimitReached = computed(() => storageUsagePercentage.value >= 100);

    const criticalLimitsReached = computed(() => isAiLimitReached.value || isAgentsLimitReached.value || isStorageLimitReached.value);

    // ============================================
    // HELPERS
    // ============================================
    const _extractError = (error) => {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
            const first = Object.values(error.response.data.errors)[0];
            return Array.isArray(first) ? first[0] : first;
        }
        if (error.response?.data?.message) {
            errors.value = { general: [error.response.data.message] };
            return error.response.data.message;
        }
        return 'Something went wrong. Please try again.';
    };

    // ============================================
    // TENANT - PLANS
    // ============================================
    const fetchPlans = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getPlans(params);
            if (response.data.success) {
                plans.value = response.data.data || [];
                return plans.value;
            }
        } catch (error) {
            console.error('fetchPlans:', error);
            toast.error('Failed to load plans');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // ============================================
    // TENANT - SUBSCRIPTION
    // ============================================
    const fetchSubscription = async () => {
        loading.value = true;
        errors.value = {};
        try {
            const response = await billingService.getCurrentSubscription();
            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan ?? null;
                return subscription.value;
            }
        } catch (error) {
            if ([402, 404].includes(error.response?.status)) {
                subscription.value = null;
                currentPlan.value = null;
                return null;
            }
            console.error('fetchSubscription:', error);
            toast.error('Failed to load subscription');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createSubscription = async (data) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.createSubscription(data);
            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan;
                toast.success('Subscription created successfully!');
                return response.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const upgradeSubscription = async (planId, cycle = null) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.upgradeSubscription(planId, cycle);
            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan;
                toast.success('Subscription upgraded successfully!');
                return response.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const downgradeSubscription = async (planId) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.downgradeSubscription(planId);
            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan;
                toast.success('Subscription downgrade scheduled!');
                return response.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const cancelSubscription = async (immediately = false) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.cancelSubscription(immediately);
            if (response.data.success) {
                subscription.value = response.data.data;
                toast.success(response.data.message || 'Subscription cancelled');
                return response.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resumeSubscription = async () => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.resumeSubscription();
            if (response.data.success) {
                subscription.value = response.data.data;
                toast.success('Subscription resumed successfully!');
                return response.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const validateCoupon = async (code) => {
        try {
            const response = await billingService.validateCoupon(code);
            return {
                valid: response.data.success,
                message: response.data.message,
                data: response.data.data
            };
        } catch (error) {
            console.error('validateCoupon:', error);
            return {
                valid: false,
                message: error.response?.data?.message || 'Invalid coupon'
            };
        }
    };

    // ============================================
    // TENANT - CHECKOUT
    // ============================================
    const createCheckout = async (planId, billingCycle = 'monthly', gateway = null) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.checkout({
                plan_id: planId,
                billing_cycle: billingCycle,
                gateway
            });
            if (response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const confirmCheckout = async () => {
        saving.value = true;
        try {
            await fetchSubscription();
            return subscription.value;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // TENANT - USAGE
    // ============================================
    const fetchUsage = async () => {
        loading.value = true;
        try {
            const response = await billingService.getUsage();
            if (response.data.success) {
                usageSummary.value = response.data.data;
                return usageSummary.value;
            }
        } catch (error) {
            // 402 = no subscription yet — this is a valid state, not an error
            if (error.response?.status === 402) {
                usageSummary.value = null;
                return null;
            }
            console.error('fetchUsage:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // ============================================
    // TENANT - INVOICES
    // ============================================
    const fetchInvoices = async (params = {}) => {
        loading.value = true;
        try {
            const mergedParams = { ...filters, ...params };
            const response = await billingService.getInvoices(mergedParams);
            if (response.data.success) {
                invoices.value = response.data.data || [];
                invoicesPagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('fetchInvoices:', error);
            toast.error('Failed to load invoices');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchInvoiceStatistics = async () => {
        try {
            const response = await billingService.getInvoiceStatistics();
            if (response.data.success) {
                invoiceStats.value = response.data.data;
                return invoiceStats.value;
            }
        } catch (error) {
            console.error('fetchInvoiceStatistics:', error);
            return null;
        }
    };

    // ============================================
    // TENANT - PAYMENTS
    // ============================================
    const fetchPayments = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getPayments(params);
            if (response.data.success) {
                payments.value = response.data.data || [];
                paymentsPagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('fetchPayments:', error);
            toast.error('Failed to load payments');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchPaymentStatistics = async () => {
        try {
            const response = await billingService.getPaymentStatistics();
            if (response.data.success) {
                paymentStats.value = response.data.data;
                return paymentStats.value;
            }
        } catch (error) {
            console.error('fetchPaymentStatistics:', error);
            return null;
        }
    };

    // ============================================
    // ADMIN - PLANS
    // ============================================
    const fetchAdminPlans = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminPlans(params);
            if (response.data.success !== false) {
                adminPlans.value = response.data.data || [];
                adminPagination.plans = response.data.meta || null;
                return adminPlans.value;
            }
        } catch (error) {
            console.error('fetchAdminPlans:', error);
            toast.error('Failed to load plans');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createAdminPlan = async (payload) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.createAdminPlan(payload);
            toast.success('Plan created');
            return response.data.data;
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateAdminPlan = async (id, payload) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.updateAdminPlan(id, payload);
            toast.success('Plan updated');
            return response.data.data;
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteAdminPlan = async (id) => {
        saving.value = true;
        try {
            await billingService.deleteAdminPlan(id);
            adminPlans.value = adminPlans.value.filter((p) => p.id !== id);
            toast.success('Plan deleted');
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ADMIN - SUBSCRIPTIONS / INVOICES / PAYMENTS
    // ============================================
    const fetchAdminSubscriptions = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminSubscriptions(params);
            if (response.data.success !== false) {
                adminSubscriptions.value = response.data.data || [];
                adminPagination.subscriptions = response.data.meta || null;
                return adminSubscriptions.value;
            }
        } catch (error) {
            toast.error('Failed to load subscriptions');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAdminInvoices = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminInvoices(params);
            if (response.data.success !== false) {
                adminInvoices.value = response.data.data || [];
                adminPagination.invoices = response.data.meta || null;
                return adminInvoices.value;
            }
        } catch (error) {
            toast.error('Failed to load invoices');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAdminPayments = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminPayments(params);
            if (response.data.success !== false) {
                adminPayments.value = response.data.data || [];
                adminPagination.payments = response.data.meta || null;
                return adminPayments.value;
            }
        } catch (error) {
            toast.error('Failed to load payments');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // ============================================
    // ADMIN - COUPONS
    // ============================================
    const fetchAdminCoupons = async (params = {}) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminCoupons(params);
            if (response.data.success !== false) {
                adminCoupons.value = response.data.data || [];
                adminPagination.coupons = response.data.meta || null;
                return adminCoupons.value;
            }
        } catch (error) {
            toast.error('Failed to load coupons');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createAdminCoupon = async (payload) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.createAdminCoupon(payload);
            toast.success('Coupon created');
            return response.data.data;
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateAdminCoupon = async (id, payload) => {
        saving.value = true;
        errors.value = {};
        try {
            const response = await billingService.updateAdminCoupon(id, payload);
            toast.success('Coupon updated');
            return response.data.data;
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteAdminCoupon = async (id) => {
        saving.value = true;
        try {
            await billingService.deleteAdminCoupon(id);
            adminCoupons.value = adminCoupons.value.filter((c) => c.id !== id);
            toast.success('Coupon deleted');
        } catch (error) {
            toast.error(_extractError(error));
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ADMIN - ANALYTICS
    // ============================================
    const fetchAdminAnalytics = async () => {
        loading.value = true;
        try {
            const response = await billingService.getAdminAnalytics();
            if (response.data.success) {
                analytics.value = response.data.data;
                return analytics.value;
            }
        } catch (error) {
            toast.error('Failed to load analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAdminTenantSummary = async (tenantId) => {
        loading.value = true;
        try {
            const response = await billingService.getAdminTenantSummary(tenantId);
            return response.data.data;
        } catch (error) {
            toast.error('Failed to load tenant summary');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // ============================================
    // UTILITY
    // ============================================
    const resetFilters = () => {
        Object.assign(filters, {
            status: null,
            date_from: null,
            date_to: null,
            per_page: 20
        });
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    const resetStore = () => {
        plans.value = [];
        currentPlan.value = null;
        subscription.value = null;
        usageSummary.value = null;
        invoices.value = [];
        payments.value = [];
        invoicesPagination.value = null;
        paymentsPagination.value = null;
        adminPlans.value = [];
        adminSubscriptions.value = [];
        adminInvoices.value = [];
        adminPayments.value = [];
        adminCoupons.value = [];
        analytics.value = null;
        Object.assign(adminPagination, {
            plans: null,
            subscriptions: null,
            invoices: null,
            payments: null,
            coupons: null
        });
        errors.value = {};
        resetFilters();
    };

    // ============================================
    // RETURN
    // ============================================
    return {
        // Tenant state
        plans,
        currentPlan,
        subscription,
        usageSummary,
        invoices,
        payments,
        invoiceStats,
        paymentStats,
        invoicesPagination,
        paymentsPagination,

        // Admin state
        adminPlans,
        adminSubscriptions,
        adminInvoices,
        adminPayments,
        adminCoupons,
        analytics,
        adminPagination,

        // UI state
        loading,
        saving,
        errors,
        filters,

        // Getters
        hasSubscription,
        isActive,
        isTrialing,
        isCancelled,
        isExpired,
        planName,
        daysRemaining,
        trialDaysRemaining,
        usage,
        activePlans,
        aiUsagePercentage,
        agentsUsagePercentage,
        customersUsagePercentage,
        widgetsUsagePercentage,
        documentsUsagePercentage,
        storageUsagePercentage,
        conversationsUsagePercentage,
        isAiLimitReached,
        isAgentsLimitReached,
        isDocumentsLimitReached,
        isStorageLimitReached,
        criticalLimitsReached,

        // Tenant actions
        fetchPlans,
        fetchSubscription,
        createSubscription,
        upgradeSubscription,
        downgradeSubscription,
        cancelSubscription,
        resumeSubscription,
        validateCoupon,
        createCheckout,
        confirmCheckout,
        fetchUsage,
        fetchInvoices,
        fetchInvoiceStatistics,
        fetchPayments,
        fetchPaymentStatistics,

        // Admin actions
        fetchAdminPlans,
        createAdminPlan,
        updateAdminPlan,
        deleteAdminPlan,
        fetchAdminSubscriptions,
        fetchAdminInvoices,
        fetchAdminPayments,
        fetchAdminCoupons,
        createAdminCoupon,
        updateAdminCoupon,
        deleteAdminCoupon,
        fetchAdminAnalytics,
        fetchAdminTenantSummary,

        // Utility
        resetFilters,
        getFieldError,
        resetStore
    };
});
