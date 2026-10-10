<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    testing: { type: Boolean, default: false }
});

defineEmits(['test']);

const statusClass = computed(() => {
    switch (props.item.status) {
        case 'ok':
        case 'configured':
            return 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300';
        case 'error':
            return 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300';
        case 'not_configured':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
        default:
            return 'bg-surface-200 text-surface-700 dark:bg-surface-700 dark:text-surface-200';
    }
});

const statusLabel = computed(() => {
    switch (props.item.status) {
        case 'ok':
            return 'Healthy';
        case 'configured':
            return 'Configured';
        case 'error':
            return 'Error';
        case 'not_configured':
            return 'Not configured';
        default:
            return 'Unknown';
    }
});
</script>

<template>
    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex flex-col gap-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="font-semibold text-surface-900 dark:text-surface-0">{{ item.label }}</div>
                <div class="text-xs uppercase tracking-wide text-surface-400">{{ item.method }} check</div>
            </div>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap" :class="statusClass">
                {{ statusLabel }}
            </span>
        </div>

        <p class="text-sm text-surface-600 dark:text-surface-300 flex-1">{{ item.detail }}</p>

        <div class="flex items-center justify-between gap-3">
            <a v-if="item.docs" :href="item.docs" target="_blank" rel="noopener" class="text-xs text-primary hover:underline">
                Documentation
            </a>
            <span v-else></span>
            <Button type="button" size="small" label="Test" icon="pi pi-bolt" severity="secondary" outlined :loading="testing" @click="$emit('test', item.provider)" />
        </div>
    </div>
</template>
