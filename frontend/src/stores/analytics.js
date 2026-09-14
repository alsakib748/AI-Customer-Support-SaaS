// src/stores/analytics.js

import { defineStore } from 'pinia';
import { ref, reactive, computed } from 'vue';
import analyticsService from '@/services/analyticsService';
import { toast } from 'vue3-toastify';

export const useAnalyticsStore = defineStore('analytics', () => {
    // State
    const overview = ref(null);
    const conversations = ref(null);
    const customers = ref(null);
    const agents = ref(null);
    const tickets = ref(null);
    const ai = ref(null);
    const widget = ref(null);
    const knowledgeBase = ref(null);

    const meta = ref(null);
    const loading = ref(false);
    const errors = ref({});

    const filters = reactive({
        period: '30d',
        from: null,
        to: null,
        interval: null,
        channel: null,
        status: null,
        priority: null,
        agent_id: null
    });

    // Getters
    const currentPeriodLabel = computed(() => meta.value?.label || 'Custom Range');

    // Actions
    const fetchOverview = async (extra = {}) => {
        loading.value = true;
        errors.value = {};
        try {
            const response = await analyticsService.getOverview({ ...filters, ...extra });
            if (response.data.success) {
                overview.value = response.data.data;
                meta.value = response.data.meta;
            }
            return overview.value;
        } catch (error) {
            handleError(error, 'Failed to load analytics overview');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchConversations = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getConversations({ ...filters, ...extra });
            if (response.data.success) {
                conversations.value = response.data.data;
                meta.value = response.data.meta;
            }
            return conversations.value;
        } catch (error) {
            handleError(error, 'Failed to load conversation analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchCustomers = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getCustomers({ ...filters, ...extra });
            if (response.data.success) {
                customers.value = response.data.data;
                meta.value = response.data.meta;
            }
            return customers.value;
        } catch (error) {
            handleError(error, 'Failed to load customer analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAgents = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getAgents({ ...filters, ...extra });
            if (response.data.success) {
                agents.value = response.data.data;
                meta.value = response.data.meta;
            }
            return agents.value;
        } catch (error) {
            handleError(error, 'Failed to load agent analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchTickets = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getTickets({ ...filters, ...extra });
            if (response.data.success) {
                tickets.value = response.data.data;
                meta.value = response.data.meta;
            }
            return tickets.value;
        } catch (error) {
            handleError(error, 'Failed to load ticket analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchAI = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getAI({ ...filters, ...extra });
            if (response.data.success) {
                ai.value = response.data.data;
                meta.value = response.data.meta;
            }
            return ai.value;
        } catch (error) {
            handleError(error, 'Failed to load AI analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchWidget = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getWidget({ ...filters, ...extra });
            if (response.data.success) {
                widget.value = response.data.data;
                meta.value = response.data.meta;
            }
            return widget.value;
        } catch (error) {
            handleError(error, 'Failed to load widget analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchKnowledgeBase = async (extra = {}) => {
        loading.value = true;
        try {
            const response = await analyticsService.getKnowledgeBase({ ...filters, ...extra });
            if (response.data.success) {
                knowledgeBase.value = response.data.data;
                meta.value = response.data.meta;
            }
            return knowledgeBase.value;
        } catch (error) {
            handleError(error, 'Failed to load KB analytics');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const setFilters = (newFilters) => {
        Object.assign(filters, newFilters);
    };

    const resetFilters = () => {
        filters.period = '30d';
        filters.from = null;
        filters.to = null;
        filters.interval = null;
        filters.channel = null;
        filters.status = null;
        filters.priority = null;
        filters.agent_id = null;
    };

    const handleError = (error, fallback) => {
        const message = error.response?.data?.message || fallback;
        errors.value = { general: [message] };
        toast.error(message);
    };

    return {
        // State
        overview,
        conversations,
        customers,
        agents,
        tickets,
        ai,
        widget,
        knowledgeBase,
        meta,
        loading,
        errors,
        filters,

        // Getters
        currentPeriodLabel,

        // Actions
        fetchOverview,
        fetchConversations,
        fetchCustomers,
        fetchAgents,
        fetchTickets,
        fetchAI,
        fetchWidget,
        fetchKnowledgeBase,
        setFilters,
        resetFilters
    };
});
