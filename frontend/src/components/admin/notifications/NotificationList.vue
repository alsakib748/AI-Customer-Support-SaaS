<script setup>
import NotificationItem from './NotificationItem.vue';
import NotificationEmptyState from './NotificationEmptyState.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    managing: { type: Boolean, default: false },
    meta: { type: Object, default: () => ({}) },
    hasActiveFilters: { type: Boolean, default: false }
});

const emit = defineEmits(['read', 'unread', 'remove', 'open', 'page', 'clear-filters']);

const goTo = (page) => {
    const current = props.meta?.current_page ?? 1;
    const last = props.meta?.last_page ?? 1;
    if (page < 1 || page > last || page === current) return;
    emit('page', page);
};
</script>

<template>
    <div>
        <div v-if="loading && !items.length" class="text-surface-500 dark:text-surface-400 text-sm py-12 text-center">Loading notifications…</div>

        <NotificationEmptyState v-else-if="!items.length && hasActiveFilters" icon="pi pi-filter-slash" title="No matching notifications" message="Try adjusting or clearing the active filters." />

        <NotificationEmptyState v-else-if="!items.length" icon="pi pi-bell" title="You are all caught up" message="New platform notifications will appear here." />

        <ul v-else class="space-y-3">
            <NotificationItem
                v-for="notification in items"
                :key="notification.id"
                :notification="notification"
                :managing="managing"
                @read="emit('read', $event)"
                @unread="emit('unread', $event)"
                @remove="emit('remove', $event)"
                @open="emit('open', $event)"
            />
        </ul>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between mt-5 pt-4 border-t border-surface-200 dark:border-surface-700">
            <span class="text-sm text-surface-500 dark:text-surface-400"> Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total </span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border border-surface-200 dark:border-surface-700 hover:bg-surface-100 dark:hover:bg-surface-800 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="meta.current_page <= 1 || loading"
                    @click="goTo(meta.current_page - 1)"
                >
                    <i class="pi pi-angle-left"></i> Prev
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border border-surface-200 dark:border-surface-700 hover:bg-surface-100 dark:hover:bg-surface-800 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="meta.current_page >= meta.last_page || loading"
                    @click="goTo(meta.current_page + 1)"
                >
                    Next <i class="pi pi-angle-right"></i>
                </button>
            </div>
        </div>
    </div>
</template>
