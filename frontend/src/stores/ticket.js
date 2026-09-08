// src/stores/ticket.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import ticketService from '@/services/ticketService';
import { toast } from 'vue3-toastify';

export const useTicketStore = defineStore('ticket', () => {
    // ============================================
    // STATE
    // ============================================

    const tickets = ref([]);
    const currentTicket = ref(null);
    const comments = ref([]);
    const statistics = ref({
        total: 0,
        open: 0,
        in_progress: 0,
        pending: 0,
        resolved: 0,
        closed: 0,
        unassigned: 0,
        overdue: 0,
        by_priority: {},
        by_type: {},
        by_source: {}
    });
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        status: null,
        priority: null,
        type: null,
        source: null,
        assigned_user_id: null,
        unassigned: false,
        overdue: false,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    const commentFilters = ref({
        type: null
    });

    // ============================================
    // GETTERS
    // ============================================

    const totalTickets = computed(() => pagination.value?.total || 0);
    const hasTickets = computed(() => tickets.value.length > 0);
    const openCount = computed(() => statistics.value?.open || 0);
    const inProgressCount = computed(() => statistics.value?.in_progress || 0);
    const pendingCount = computed(() => statistics.value?.pending || 0);
    const resolvedCount = computed(() => statistics.value?.resolved || 0);
    const overdueCount = computed(() => statistics.value?.overdue || 0);
    const unassignedCount = computed(() => statistics.value?.unassigned || 0);

    // ============================================
    // ACTIONS
    // ============================================

    const fetchTickets = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await ticketService.getTickets(mergedParams);

            if (response.data.success) {
                tickets.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch tickets:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load tickets'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load tickets');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchTicket = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await ticketService.getTicket(id);

            if (response.data.success) {
                currentTicket.value = response.data.data;
                return currentTicket.value;
            }
        } catch (error) {
            console.error('Failed to fetch ticket:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load ticket'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load ticket');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createTicket = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await ticketService.createTicket(data);

            if (response.data.success) {
                // Add to list
                tickets.value.unshift(response.data.data);
                toast.success(response.data.message || 'Ticket created successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create ticket:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to create ticket'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateTicket = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await ticketService.updateTicket(id, data);

            if (response.data.success) {
                // Update in list
                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = response.data.data;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = response.data.data;
                }

                toast.success(response.data.message || 'Ticket updated successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update ticket:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update ticket'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteTicket = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await ticketService.deleteTicket(id);

            if (response.data.success) {
                tickets.value = tickets.value.filter((t) => t.id !== id);

                if (currentTicket.value?.id === id) {
                    currentTicket.value = null;
                }

                toast.success(response.data.message || 'Ticket deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete ticket:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete ticket'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const assignTicket = async (id, userId) => {
        saving.value = true;

        try {
            const response = await ticketService.assignTicket(id, userId);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket assigned successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to assign ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to assign ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const unassignTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.unassignTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket unassigned successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to unassign ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to unassign ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const startTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.startTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket started successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to start ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to start ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const pendingTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.pendingTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket set to pending');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to set ticket pending:', error);
            toast.error(error.response?.data?.message || 'Failed to set ticket pending');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resolveTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.resolveTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket resolved successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to resolve ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to resolve ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const reopenTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.reopenTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket reopened successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to reopen ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to reopen ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const closeTicket = async (id) => {
        saving.value = true;

        try {
            const response = await ticketService.closeTicket(id);

            if (response.data.success) {
                const updated = response.data.data;

                const index = tickets.value.findIndex((t) => t.id === id);
                if (index !== -1) {
                    tickets.value[index] = updated;
                }

                if (currentTicket.value?.id === id) {
                    currentTicket.value = updated;
                }

                toast.success(response.data.message || 'Ticket closed successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to close ticket:', error);
            toast.error(error.response?.data?.message || 'Failed to close ticket');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const fetchStatistics = async () => {
        try {
            const response = await ticketService.getStatistics();

            if (response.data.success) {
                statistics.value = response.data.data || statistics.value;
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch ticket statistics:', error);
            toast.error('Failed to load ticket statistics');
            return null;
        }
    };

    const fetchComments = async (ticketId, params = {}) => {
        loading.value = true;

        try {
            const mergedParams = { ...commentFilters.value, ...params };
            const response = await ticketService.getComments(ticketId, mergedParams);

            if (response.data.success) {
                comments.value = response.data.data || [];
                return comments.value;
            }
        } catch (error) {
            console.error('Failed to fetch comments:', error);
            toast.error('Failed to load comments');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const addComment = async (ticketId, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await ticketService.addComment(ticketId, data);

            if (response.data.success) {
                comments.value.push(response.data.data);
                toast.success(response.data.message || 'Comment added successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to add comment:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to add comment'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to add comment');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteComment = async (ticketId, commentId) => {
        saving.value = true;

        try {
            const response = await ticketService.deleteComment(ticketId, commentId);

            if (response.data.success) {
                comments.value = comments.value.filter((c) => c.id !== commentId);
                toast.success(response.data.message || 'Comment deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete comment:', error);
            toast.error(error.response?.data?.message || 'Failed to delete comment');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            priority: null,
            type: null,
            source: null,
            assigned_user_id: null,
            unassigned: false,
            overdue: false,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    const resetCommentFilters = () => {
        commentFilters.value = {
            type: null
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
        tickets,
        currentTicket,
        comments,
        statistics,
        pagination,
        loading,
        saving,
        errors,
        filters,
        commentFilters,

        // Getters
        totalTickets,
        hasTickets,
        openCount,
        inProgressCount,
        pendingCount,
        resolvedCount,
        overdueCount,
        unassignedCount,

        // Actions
        fetchTickets,
        fetchTicket,
        createTicket,
        updateTicket,
        deleteTicket,
        assignTicket,
        unassignTicket,
        startTicket,
        pendingTicket,
        resolveTicket,
        reopenTicket,
        closeTicket,
        fetchStatistics,
        fetchComments,
        addComment,
        deleteComment,
        resetFilters,
        resetCommentFilters,
        getFieldError
    };
});
