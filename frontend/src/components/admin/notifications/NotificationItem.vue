<script setup>
import { computed } from 'vue';
import { severityStyle, timeAgo } from '@/utils/notificationMeta';

const props = defineProps({
    notification: { type: Object, required: true },
    managing: { type: Boolean, default: false }
});

const emit = defineEmits(['read', 'unread', 'remove', 'open']);

const isUnread = computed(() => !props.notification.read_at);
const severity = computed(() => severityStyle(props.notification.severity));

const toggleRead = () => {
    if (isUnread.value) {
        emit('read', props.notification);
    } else {
        emit('unread', props.notification);
    }
};

const open = () => {
    if (isUnread.value) {
        emit('read', props.notification);
    }
    emit('open', props.notification);
};
</script>

<template>
    <li
        class="group relative flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition-colors"
        :class="isUnread ? 'border-primary-200 dark:border-primary-500/30 bg-primary-50/40 dark:bg-primary-500/5' : 'border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800/50'"
        @click="open"
    >
        <span class="mt-0.5 h-2.5 w-2.5 rounded-full shrink-0" :class="isUnread ? severity.dot : 'bg-transparent'"></span>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-surface-500 dark:text-surface-400">
                    <i :class="notification.category_icon"></i>
                    {{ notification.category_label }}
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="severity.badge">
                    {{ notification.severity_label }}
                </span>
                <span class="text-xs text-surface-400 dark:text-surface-500 ms-auto">{{ timeAgo(notification.created_at) }}</span>
            </div>

            <div class="mt-1 font-semibold text-surface-900 dark:text-surface-0 truncate" :class="{ 'font-bold': isUnread }">
                {{ notification.title }}
            </div>
            <p v-if="notification.message" class="text-sm text-surface-600 dark:text-surface-300 mt-0.5 line-clamp-2">
                {{ notification.message }}
            </p>
        </div>

        <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
            <button
                type="button"
                class="h-8 w-8 rounded-lg flex items-center justify-center text-surface-500 hover:bg-surface-100 dark:hover:bg-surface-700 hover:text-primary"
                :title="isUnread ? 'Mark as read' : 'Mark as unread'"
                :disabled="managing"
                @click.stop="toggleRead"
            >
                <i :class="isUnread ? 'pi pi-envelope' : 'pi pi-envelope-open'"></i>
            </button>
            <button type="button" class="h-8 w-8 rounded-lg flex items-center justify-center text-surface-500 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600" title="Delete" :disabled="managing" @click.stop="emit('remove', notification)">
                <i class="pi pi-trash"></i>
            </button>
        </div>
    </li>
</template>
