// src/stores/ai.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import aiService from '@/services/aiService';
import { toast } from 'vue3-toastify';

export const useAIStore = defineStore('ai', () => {
    // ============================================
    // STATE
    // ============================================

    const configuration = ref({
        provider: 'openai',
        model: 'gpt-4o-mini',
        enabled: true,
        auto_reply_enabled: true,
        auto_escalation_enabled: true,
        streaming_enabled: true,
        knowledge_base_enabled: true,
        temperature: 0.7,
        max_tokens: 2000,
        system_prompt: '',
        custom_instructions: ''
    });

    const usage = ref({
        total_requests: 0,
        total_tokens: 0,
        total_cost: 0,
        by_provider: [],
        by_model: [],
        daily_usage: []
    });

    const health = ref({
        total_requests: 0,
        success_rate: 0,
        failure_rate: 0,
        avg_response_time: 0,
        status: 'healthy'
    });

    const analytics = ref({
        total_requests: 0,
        total_cost: 0,
        avg_cost_per_request: 0,
        trend: []
    });

    const logs = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const testing = ref(false);
    const streaming = ref(false);
    const currentResponse = ref('');
    const errors = ref({});

    // ============================================
    // GETTERS
    // ============================================

    const isAIEnabled = computed(() => configuration.value.enabled);
    const isAutoReplyEnabled = computed(() => configuration.value.auto_reply_enabled);
    const isStreamingEnabled = computed(() => configuration.value.streaming_enabled);
    const healthStatus = computed(() => health.value.status);
    const healthStatusColor = computed(() => {
        const colors = {
            healthy: 'success',
            degraded: 'warning',
            unhealthy: 'danger'
        };
        return colors[health.value.status] || 'secondary';
    });

    // ============================================
    // ACTIONS
    // ============================================

    const fetchConfiguration = async () => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await aiService.getConfiguration();

            if (response.data.success) {
                configuration.value = response.data.data;
                return configuration.value;
            }
        } catch (error) {
            console.error('Failed to fetch AI configuration:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load AI configuration'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load AI configuration');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const updateConfiguration = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await aiService.updateConfiguration(data);

            if (response.data.success) {
                configuration.value = response.data.data;
                toast.success(response.data.message || 'AI configuration updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update AI configuration:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update AI configuration'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update AI configuration');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const testAI = async (message) => {
        testing.value = true;
        errors.value = {};

        try {
            const response = await aiService.testAI(message);

            if (response.data.success) {
                toast.success(response.data.message || 'AI test completed successfully ✅');
                return response.data.data;
            }
        } catch (error) {
            console.error('AI test failed:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['AI test failed'] };
            }

            toast.error(errors.value.general?.[0] || 'AI test failed');
            throw error;
        } finally {
            testing.value = false;
        }
    };

    const fetchUsage = async (params = {}) => {
        loading.value = true;

        try {
            const response = await aiService.getUsage(params);

            if (response.data.success) {
                usage.value = response.data.data;
                return usage.value;
            }
        } catch (error) {
            console.error('Failed to fetch AI usage:', error);
            toast.error('Failed to load AI usage');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchHealth = async () => {
        try {
            const response = await aiService.getHealth();

            if (response.data.success) {
                health.value = response.data.data;
                return health.value;
            }
        } catch (error) {
            console.error('Failed to fetch AI health:', error);
            toast.error('Failed to load AI health metrics');
            throw error;
        }
    };

    const fetchAnalytics = async (params = {}) => {
        try {
            const response = await aiService.getAnalytics(params);

            if (response.data.success) {
                analytics.value = response.data.data;
                return analytics.value;
            }
        } catch (error) {
            console.error('Failed to fetch AI analytics:', error);
            toast.error('Failed to load AI analytics');
            throw error;
        }
    };

    const fetchLogs = async (params = {}) => {
        loading.value = true;

        try {
            const response = await aiService.getLogs(params);

            if (response.data.success) {
                logs.value = response.data.data || [];
                return logs.value;
            }
        } catch (error) {
            console.error('Failed to fetch AI logs:', error);
            toast.error('Failed to load AI logs');
            throw error;
        } finally {
            loading.value = false;
        }
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
        configuration,
        usage,
        health,
        analytics,
        logs,
        loading,
        saving,
        testing,
        streaming,
        currentResponse,
        errors,

        // Getters
        isAIEnabled,
        isAutoReplyEnabled,
        isStreamingEnabled,
        healthStatus,
        healthStatusColor,

        // Actions
        fetchConfiguration,
        updateConfiguration,
        testAI,
        fetchUsage,
        fetchHealth,
        fetchAnalytics,
        fetchLogs,
        getFieldError
    };
});
