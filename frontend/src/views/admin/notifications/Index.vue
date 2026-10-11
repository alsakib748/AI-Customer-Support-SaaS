<script setup>
import { onMounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useNotificationStore } from '@/stores/notification';
import NotificationFilters from '@/components/admin/notifications/NotificationFilters.vue';
import NotificationList from '@/components/admin/notifications/NotificationList.vue';

const store = useNotificationStore();
const { items, meta, filters, loading, managing, unreadCount, error, hasActiveFilters } = storeToRefs(store);
const router = useRouter();

const load = (page = 1) => store.fetchPage(page).catch(() => {});

const onFiltersUpdate = (partial) => {
    store.setFilters(partial);
};

const onFiltersReset = () => {
    store.resetFilters();
};

const refreshIfFiltered = () => {
    if (filters.value.status !== 'all') {
        load(meta.value.current_page ?? 1);
    }
};

const markRead = async (notification) => {
    try {
        await store.markRead(notification.id);
        refreshIfFiltered();
    } catch {
        toast.error('Failed to mark as read');
    }
};

const markUnread = async (notification) => {
    try {
        await store.markUnread(notification.id);
        refreshIfFiltered();
    } catch {
        toast.error('Failed to mark as unread');
    }
};

const remove = async (notification) => {
    try {
        await store.remove(notification.id);
        toast.success('Notification deleted');
        if (filters.value.status !== 'all') {
            load(meta.value.current_page ?? 1);
        }
    } catch {
        toast.error('Failed to delete notification');
    }
};

const open = (notification) => {
    if (notification.action_url) {
        router.push(notification.action_url);
    }
};

const markAllRead = async () => {
    try {
        await store.markAllRead();
        toast.success('All notifications marked as read');
        refreshIfFiltered();
    } catch {
        toast.error('Failed to mark all as read');
    }
};

watch(filters, () => load(1), { deep: true });

onMounted(() => load(1));
</script>

<template>
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">Notifications</h1>
                <p class="text-surface-600 dark:text-surface-400 mt-2">Platform-wide alerts across health, billing, tenants, security and integrations.</p>
            </div>
            <Button label="Mark all as read" icon="pi pi-check" severity="secondary" outlined :disabled="managing || !unreadCount" :loading="managing" @click="markAllRead" />
        </div>

        <div class="mb-5">
            <NotificationFilters :filters="filters" :unread-count="unreadCount" @update="onFiltersUpdate" @reset="onFiltersReset" />
        </div>

        <div v-if="error" class="mb-4 rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ error }}
        </div>

        <Card>
            <template #content>
                <NotificationList
                    :items="items"
                    :loading="loading"
                    :managing="managing"
                    :meta="meta"
                    :has-active-filters="hasActiveFilters"
                    @read="markRead"
                    @unread="markUnread"
                    @remove="remove"
                    @open="open"
                    @page="load"
                    @clear-filters="onFiltersReset"
                />
            </template>
        </Card>
    </div>
</template>
