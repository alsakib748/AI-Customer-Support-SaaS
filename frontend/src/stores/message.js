// src/stores/message.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import messageService from '@/services/messageService';
import { toast } from 'vue3-toastify';

export const useMessageStore = defineStore('message', () => {
    // ============================================
    // STATE
    // ============================================

    const messages = ref([]);
    const currentMessage = ref(null);
    const pagination = ref(null);
    const loading = ref(false);
    const sending = ref(false);
    const errors = ref({});

    const filters = ref({
        message_type: null,
        sender_type: null,
        per_page: 30
    });

    // ============================================
    // GETTERS
    // ============================================

    const totalMessages = computed(() => pagination.value?.total || 0);
    const hasMessages = computed(() => messages.value.length > 0);
    const customerMessages = computed(() => messages.value.filter((m) => m.sender.type === 'customer'));
    const agentMessages = computed(() => messages.value.filter((m) => m.sender.type === 'agent'));
    const aiMessages = computed(() => messages.value.filter((m) => m.sender.type === 'ai'));
    const internalMessages = computed(() => messages.value.filter((m) => m.is_internal));

    // ============================================
    // ACTIONS
    // ============================================

    const fetchMessages = async (conversationId, params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await messageService.getMessages(conversationId, mergedParams);

            if (response.data.success) {
                messages.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch messages:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load messages'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load messages');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const sendMessage = async (conversationId, data) => {
        // console.log('Store: sendMessage called', { conversationId, data });
        sending.value = true;
        errors.value = {};

        try {
            const response = await messageService.sendMessage(conversationId, data);

            if (response.data.success) {
                // Append message to list
                messages.value.push(response.data.data);
                toast.success(response.data.message || 'Message sent successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to send message:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to send message'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to send message');
            throw error;
        } finally {
            sending.value = false;
        }
    };

    const addNote = async (conversationId, data) => {
        sending.value = true;
        errors.value = {};

        try {
            const response = await messageService.addNote(conversationId, data);

            if (response.data.success) {
                messages.value.push(response.data.data);
                toast.success(response.data.message || 'Note added successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to add note:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to add note'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to add note');
            throw error;
        } finally {
            sending.value = false;
        }
    };

    const deleteMessage = async (id) => {
        sending.value = true;
        errors.value = {};

        try {
            const response = await messageService.deleteMessage(id);

            if (response.data.success) {
                messages.value = messages.value.filter((m) => m.id !== id);
                toast.success(response.data.message || 'Message deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete message:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete message'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete message');
            throw error;
        } finally {
            sending.value = false;
        }
    };

    const resetFilters = () => {
        filters.value = {
            message_type: null,
            sender_type: null,
            per_page: 30
        };
    };

    const clearMessages = () => {
        messages.value = [];
        currentMessage.value = null;
        pagination.value = null;
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
        messages,
        currentMessage,
        pagination,
        loading,
        sending,
        errors,
        filters,

        // Getters
        totalMessages,
        hasMessages,
        customerMessages,
        agentMessages,
        aiMessages,
        internalMessages,

        // Actions
        fetchMessages,
        sendMessage,
        addNote,
        deleteMessage,
        resetFilters,
        clearMessages,
        getFieldError
    };
});
