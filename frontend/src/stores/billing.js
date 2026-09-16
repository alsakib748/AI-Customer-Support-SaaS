// src/stores/billing.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import billingService from '@/services/billingService';
import { toast } from 'vue3-toastify';

export const useBillingStore = defineStore('billing', () => {
    // ============================================
    // STATE
    // ============================================

    const plans = ref([]);
    const currentPlan = ref(null);
    const subscription = ref(null);
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
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        status: null,
        date_from: null,
        date_to: null,
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================

    const hasSubscription = computed(() => !!subscription.value);
    const isActive = computed(() => subscription.value?.is_active ?? false);
    const isTrialing = computed(() => subscription.value?.is_trialing ?? false);
    const isCancelled = computed(() => subscription.value?.is_cancelled ?? false);
    const isExpired = computed(() => subscription.value?.is_expired ?? false);
    const planName = computed(() => subscription.value?.plan?.name ?? 'No Plan');
    const daysRemaining = computed(() => subscription.value?.days_remaining ?? 0);
    const trialDaysRemaining = computed(() => subscription.value?.trial_days_remaining ?? 0);
    const usage = computed(() => subscription.value?.usage ?? {});
    const activePlans = computed(() => plans.value.filter((p) => p.is_active));

    const aiUsagePercentage = computed(() => usage.value?.ai?.percentage ?? 0);
    const agentsUsagePercentage = computed(() => usage.value?.agents?.percentage ?? 0);
    const documentsUsagePercentage = computed(() => usage.value?.documents?.percentage ?? 0);
    const storageUsagePercentage = computed(() => usage.value?.storage?.percentage ?? 0);

    const isAiLimitReached = computed(() => aiUsagePercentage.value >= 100);
    const isAgentsLimitReached = computed(() => agentsUsagePercentage.value >= 100);
    const isDocumentsLimitReached = computed(() => documentsUsagePercentage.value >= 100);

    // ============================================
    // ACTIONS - PLANS
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
            console.error('Failed to fetch plans:', error);
            toast.error('Failed to load plans');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // ============================================
    // ACTIONS - SUBSCRIPTION
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
            console.error('Failed to fetch subscription:', error);

            if (error.response?.status === 404) {
                subscription.value = null;
            } else {
                toast.error('Failed to load subscription');
            }

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
                toast.success('Subscription created successfully! 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create subscription:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create subscription');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const upgradeSubscription = async (planId) => {
        saving.value = true;

        try {
            const response = await billingService.upgradeSubscription(planId);

            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan;
                toast.success('Subscription upgraded successfully! 🚀');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to upgrade subscription:', error);
            toast.error(error.response?.data?.message || 'Failed to upgrade subscription');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const downgradeSubscription = async (planId) => {
        saving.value = true;

        try {
            const response = await billingService.downgradeSubscription(planId);

            if (response.data.success) {
                subscription.value = response.data.data;
                currentPlan.value = response.data.data?.plan;
                toast.success('Subscription downgrade scheduled! 📅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to downgrade subscription:', error);
            toast.error(error.response?.data?.message || 'Failed to downgrade subscription');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const cancelSubscription = async (immediately = false) => {
        saving.value = true;

        try {
            const response = await billingService.cancelSubscription(immediately);

            if (response.data.success) {
                subscription.value = response.data.data;
                toast.success(response.data.message || 'Subscription cancelled');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to cancel subscription:', error);
            toast.error(error.response?.data?.message || 'Failed to cancel subscription');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resumeSubscription = async () => {
        saving.value = true;

        try {
            const response = await billingService.resumeSubscription();

            if (response.data.success) {
                subscription.value = response.data.data;
                toast.success('Subscription resumed successfully! ✅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to resume subscription:', error);
            toast.error(error.response?.data?.message || 'Failed to resume subscription');
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
            return {
                valid: false,
                message: error.response?.data?.message || 'Invalid coupon'
            };
        }
    };

    // ============================================
    // ACTIONS - INVOICES
    // ============================================

    const fetchInvoices = async (params = {}) => {
        loading.value = true;

        try {
            const mergedParams = { ...filters.value, ...params };
            const response = await billingService.getInvoices(mergedParams);

            if (response.data.success) {
                invoices.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch invoices:', error);
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
            console.error('Failed to fetch invoice statistics:', error);
            return null;
        }
    };

    // ============================================
    // ACTIONS - PAYMENTS
    // ============================================

    const fetchPayments = async (params = {}) => {
        loading.value = true;

        try {
            const response = await billingService.getPayments(params);

            if (response.data.success) {
                payments.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch payments:', error);
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
            console.error('Failed to fetch payment statistics:', error);
            return null;
        }
    };

    // ============================================
    // UTILITY
    // ============================================

    const resetFilters = () => {
        filters.value = {
            status: null,
            date_from: null,
            date_to: null,
            per_page: 20
        };
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    // ============================================
    // RETURN
    // ============================================

    return {
        // State
        plans,
        currentPlan,
        subscription,
        invoices,
        payments,
        invoiceStats,
        paymentStats,
        pagination,
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
        documentsUsagePercentage,
        storageUsagePercentage,
        isAiLimitReached,
        isAgentsLimitReached,
        isDocumentsLimitReached,

        // Plan Actions
        fetchPlans,

        // Subscription Actions
        fetchSubscription,
        createSubscription,
        upgradeSubscription,
        downgradeSubscription,
        cancelSubscription,
        resumeSubscription,
        validateCoupon,

        // Invoice Actions
        fetchInvoices,
        fetchInvoiceStatistics,

        // Payment Actions
        fetchPayments,
        fetchPaymentStatistics,

        // Utility
        resetFilters,
        getFieldError
    };
});
