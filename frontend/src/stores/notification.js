// src/stores/notification.js

import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import notificationService from '@/services/notificationService';

const emptyMeta = () => ({
    unread_count: 0,
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
});

export const useNotificationStore = defineStore('notification', () => {
    // =========================================================
    // STATE
    // =========================================================
    const items = ref([]);
    const meta = ref(emptyMeta());
    const unreadCount = ref(0);

    const filters = ref({
        category: null,
        severity: null,
        status: 'all' // all | unread | read
    });

    const loading = ref(false);
    const managing = ref(false);
    const error = ref(null);

    let pollTimer = null;

    // =========================================================
    // GETTERS
    // =========================================================
    const hasUnread = computed(() => unreadCount.value > 0);
    const isEmpty = computed(() => !loading.value && items.value.length === 0);
    const hasActiveFilters = computed(() => Boolean(filters.value.category || filters.value.severity || filters.value.status !== 'all'));

    // =========================================================
    // HELPERS
    // =========================================================
    const setError = (err, fallback) => {
        error.value = err?.response?.data?.message || fallback || null;
    };

    const buildParams = (page = 1) => {
        const params = { page };

        if (filters.value.category) params.category = filters.value.category;
        if (filters.value.severity) params.severity = filters.value.severity;
        if (filters.value.status === 'unread') params.unread = 1;
        if (filters.value.status === 'read') params.read = 1;

        return params;
    };

    // =========================================================
    // ACTIONS
    // =========================================================
    const fetchPage = async (page = 1) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await notificationService.list(buildParams(page));
            items.value = data.data || [];
            meta.value = data.meta || emptyMeta();
            unreadCount.value = meta.value.unread_count ?? 0;
            return data;
        } catch (err) {
            setError(err, 'Failed to load notifications');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchUnreadCount = async () => {
        try {
            const { data } = await notificationService.unreadCount();
            unreadCount.value = data.data?.unread_count ?? 0;
            return unreadCount.value;
        } catch {
            // Silent: the bell badge is best-effort and should never toast.
            return unreadCount.value;
        }
    };

    const markRead = async (id) => {
        managing.value = true;
        try {
            const { data } = await notificationService.markAsRead(id);
            applyReadState(id, data.data?.read_at ?? new Date().toISOString());
            syncUnread(data.data?.unread_count, -1);
            return data.data;
        } catch (err) {
            setError(err, 'Failed to mark notification as read');
            throw err;
        } finally {
            managing.value = false;
        }
    };

    const markUnread = async (id) => {
        managing.value = true;
        try {
            const { data } = await notificationService.markAsUnread(id);
            applyReadState(id, null);
            syncUnread(data.data?.unread_count, +1);
            return data.data;
        } catch (err) {
            setError(err, 'Failed to mark notification as unread');
            throw err;
        } finally {
            managing.value = false;
        }
    };

    const markAllRead = async () => {
        managing.value = true;
        try {
            const payload = {};
            if (filters.value.category) payload.category = filters.value.category;
            if (filters.value.severity) payload.severity = filters.value.severity;

            const { data } = await notificationService.markAllAsRead(payload);
            items.value = items.value.map((n) => (n.read_at ? n : { ...n, read_at: new Date().toISOString() }));
            unreadCount.value = data.data?.unread_count ?? 0;
            return data.data;
        } catch (err) {
            setError(err, 'Failed to mark all as read');
            throw err;
        } finally {
            managing.value = false;
        }
    };

    const remove = async (id) => {
        managing.value = true;
        try {
            const { data } = await notificationService.remove(id);
            const removed = items.value.find((n) => n.id === id);
            items.value = items.value.filter((n) => n.id !== id);
            meta.value = { ...meta.value, total: Math.max(0, (meta.value.total || 1) - 1) };
            if (removed && !removed.read_at) {
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
            if (typeof data.data?.unread_count === 'number') {
                unreadCount.value = data.data.unread_count;
            }
            return data.data;
        } catch (err) {
            setError(err, 'Failed to delete notification');
            throw err;
        } finally {
            managing.value = false;
        }
    };

    const setFilters = (partial) => {
        filters.value = { ...filters.value, ...partial };
    };

    const resetFilters = () => {
        filters.value = { category: null, severity: null, status: 'all' };
    };

    // =========================================================
    // INTERNAL
    // =========================================================
    const applyReadState = (id, readAt) => {
        const index = items.value.findIndex((n) => n.id === id);
        if (index !== -1) {
            items.value[index] = { ...items.value[index], read_at: readAt };
        }
    };

    const syncUnread = (serverCount, delta) => {
        if (typeof serverCount === 'number') {
            unreadCount.value = serverCount;
        } else {
            unreadCount.value = Math.max(0, unreadCount.value + delta);
        }
    };

    // =========================================================
    // POLLING
    // =========================================================
    const startPolling = (intervalMs = 30000) => {
        stopPolling();
        pollTimer = setInterval(() => {
            if (!document.hidden) {
                fetchUnreadCount();
            }
        }, intervalMs);
    };

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    return {
        // State
        items,
        meta,
        unreadCount,
        filters,
        loading,
        managing,
        error,

        // Getters
        hasUnread,
        isEmpty,
        hasActiveFilters,

        // Actions
        fetchPage,
        fetchUnreadCount,
        markRead,
        markUnread,
        markAllRead,
        remove,
        setFilters,
        resetFilters,
        startPolling,
        stopPolling
    };
});
