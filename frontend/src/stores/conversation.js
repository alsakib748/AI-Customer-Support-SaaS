// src/stores/conversation.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import conversationService from '@/services/conversationService';
import { toast } from 'vue3-toastify';

export const useConversationStore = defineStore('conversation', () => {
    // ============================================
    // STATE
    // ============================================

    const conversations = ref([]);
    const currentConversation = ref(null);
    const statistics = ref({
        total: 0,
        open: 0,
        pending: 0,
        resolved: 0,
        closed: 0,
        unassigned: 0,
        urgent: 0,
        high: 0,
        by_channel: {}
    });
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        status: null,
        priority: null,
        channel: null,
        assigned_user_id: null,
        unassigned: false,
        sort: 'last_message_at',
        direction: 'desc',
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================

    const totalConversations = computed(() => pagination.value?.total || 0);
    const hasConversations = computed(() => conversations.value.length > 0);
    const openCount = computed(() => statistics.value?.open || 0);
    const pendingCount = computed(() => statistics.value?.pending || 0);
    const resolvedCount = computed(() => statistics.value?.resolved || 0);

    // ============================================
    // ACTIONS
    // ============================================

    const fetchConversations = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await conversationService.getConversations(mergedParams);

            if (response.data.success) {
                conversations.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch conversations:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load conversations'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load conversations');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchConversation = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await conversationService.getConversation(id);

            if (response.data.success) {
                currentConversation.value = response.data.data;
                return currentConversation.value;
            }
        } catch (error) {
            console.error('Failed to fetch conversation:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load conversation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load conversation');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createConversation = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await conversationService.createConversation(data);

            if (response.data.success) {
                toast.success(response.data.message || 'Conversation created successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create conversation:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to create conversation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateConversation = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await conversationService.updateConversation(id, data);

            if (response.data.success) {
                // Update in list
                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = response.data.data;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = response.data.data;
                }

                toast.success(response.data.message || 'Conversation updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update conversation:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update conversation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteConversation = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await conversationService.deleteConversation(id);

            if (response.data.success) {
                conversations.value = conversations.value.filter((c) => c.id !== id);

                if (currentConversation.value?.id === id) {
                    currentConversation.value = null;
                }

                toast.success(response.data.message || 'Conversation archived successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete conversation:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete conversation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resolveConversation = async (id) => {
        saving.value = true;

        try {
            const response = await conversationService.resolveConversation(id);

            if (response.data.success) {
                const updated = response.data.data;

                // Update in list
                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = updated;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = updated;
                }

                toast.success(response.data.message || 'Conversation resolved successfully ✅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to resolve conversation:', error);
            toast.error(error.response?.data?.message || 'Failed to resolve conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const reopenConversation = async (id) => {
        saving.value = true;

        try {
            const response = await conversationService.reopenConversation(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = updated;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = updated;
                }

                toast.success(response.data.message || 'Conversation reopened successfully 🔄');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to reopen conversation:', error);
            toast.error(error.response?.data?.message || 'Failed to reopen conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const closeConversation = async (id) => {
        saving.value = true;

        try {
            const response = await conversationService.closeConversation(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = updated;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = updated;
                }

                toast.success(response.data.message || 'Conversation closed successfully 🔒');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to close conversation:', error);
            toast.error(error.response?.data?.message || 'Failed to close conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const assignConversation = async (id, userId) => {
        saving.value = true;

        try {
            const response = await conversationService.assignConversation(id, userId);

            if (response.data.success) {
                const updated = response.data.data;

                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = updated;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = updated;
                }

                toast.success(response.data.message || 'Conversation assigned successfully 👤');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to assign conversation:', error);
            toast.error(error.response?.data?.message || 'Failed to assign conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const unassignConversation = async (id) => {
        saving.value = true;

        try {
            const response = await conversationService.unassignConversation(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = conversations.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    conversations.value[index] = updated;
                }

                if (currentConversation.value?.id === id) {
                    currentConversation.value = updated;
                }

                toast.success(response.data.message || 'Conversation unassigned successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to unassign conversation:', error);
            toast.error(error.response?.data?.message || 'Failed to unassign conversation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const fetchStatistics = async () => {
        try {
            const response = await conversationService.getStatistics();

            if (response.data.success) {
                statistics.value = response.data.data || statistics.value;
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch statistics:', error);
            toast.error('Failed to load statistics');
            return null;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            priority: null,
            channel: null,
            assigned_user_id: null,
            unassigned: false,
            sort: 'last_message_at',
            direction: 'desc',
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
        conversations,
        currentConversation,
        statistics,
        pagination,
        loading,
        saving,
        errors,
        filters,

        // Getters
        totalConversations,
        hasConversations,
        openCount,
        pendingCount,
        resolvedCount,

        // Actions
        fetchConversations,
        fetchConversation,
        createConversation,
        updateConversation,
        deleteConversation,
        resolveConversation,
        reopenConversation,
        closeConversation,
        assignConversation,
        unassignConversation,
        fetchStatistics,
        resetFilters,
        getFieldError
    };
});
