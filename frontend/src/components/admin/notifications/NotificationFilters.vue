<script setup>
import { computed } from 'vue';
import { notificationCategories, notificationSeverities, notificationStatuses } from '@/utils/notificationMeta';

defineProps({
    filters: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 }
});

const emit = defineEmits(['update', 'reset']);

const categoryOptions = computed(() => [{ label: 'All categories', value: null }, ...notificationCategories]);
const severityOptions = computed(() => [{ label: 'All severities', value: null }, ...notificationSeverities]);

const set = (partial) => emit('update', partial);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center rounded-lg border border-surface-200 dark:border-surface-700 p-0.5">
            <button
                v-for="status in notificationStatuses"
                :key="status.value"
                type="button"
                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                :class="filters.status === status.value ? 'bg-primary text-primary-contrast' : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'"
                @click="set({ status: status.value })"
            >
                {{ status.label }}
                <span v-if="status.value === 'unread' && unreadCount" class="ms-1 text-xs">({{ unreadCount }})</span>
            </button>
        </div>

        <Select :modelValue="filters.category" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="All categories" class="min-w-44" @update:modelValue="set({ category: $event })" />

        <Select :modelValue="filters.severity" :options="severityOptions" optionLabel="label" optionValue="value" placeholder="All severities" class="min-w-40" @update:modelValue="set({ severity: $event })" />

        <button v-if="filters.category || filters.severity || filters.status !== 'all'" type="button" class="text-sm font-medium text-primary hover:underline" @click="emit('reset')">Clear filters</button>
    </div>
</template>
