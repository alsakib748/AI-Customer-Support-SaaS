<script setup>
import { RouterLink } from 'vue-router';
import { severityStyle, timeAgo } from '@/utils/notificationMeta';
import NotificationEmptyState from './NotificationEmptyState.vue';

defineProps({
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    managing: { type: Boolean, default: false },
    unreadCount: { type: Number, default: 0 }
});

const emit = defineEmits(['read', 'remove', 'open', 'view-all', 'mark-all-read']);
</script>

<template>
    <div class="w-80 sm:w-96 rounded-xl shadow-lg border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-surface-200 dark:border-surface-700">
            <div class="font-semibold text-surface-900 dark:text-surface-0">
                Notifications
                <span v-if="unreadCount" class="ms-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-primary text-primary-contrast">{{ unreadCount }}</span>
            </div>
            <button v-if="unreadCount" type="button" class="text-xs font-medium text-primary hover:underline disabled:opacity-50" :disabled="managing" @click="emit('mark-all-read')">Mark all read</button>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <div v-if="loading && !items.length" class="text-surface-500 dark:text-surface-400 text-sm py-10 text-center">Loading…</div>

            <NotificationEmptyState v-else-if="!items.length" icon="pi pi-bell" title="No notifications" message="You are all caught up." />

            <ul v-else class="divide-y divide-surface-100 dark:divide-surface-800">
                <li v-for="notification in items" :key="notification.id" class="group flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/60" @click="emit('open', notification)">
                    <span class="mt-0.5 h-2.5 w-2.5 rounded-full shrink-0" :class="notification.read_at ? 'bg-transparent' : severityStyle(notification.severity).dot"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <i :class="notification.category_icon" class="text-xs text-surface-400"></i>
                            <span class="text-xs text-surface-400 dark:text-surface-500 ms-auto">{{ timeAgo(notification.created_at) }}</span>
                        </div>
                        <div class="text-sm font-medium text-surface-900 dark:text-surface-0 truncate" :class="{ 'font-semibold': !notification.read_at }">
                            {{ notification.title }}
                        </div>
                        <p v-if="notification.message" class="text-xs text-surface-500 dark:text-surface-400 line-clamp-2 mt-0.5">
                            {{ notification.message }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>

        <RouterLink to="/admin/notifications" class="block text-center text-sm font-medium text-primary py-3 border-t border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800" @click="emit('view-all')">
            View all notifications
        </RouterLink>
    </div>
</template>
