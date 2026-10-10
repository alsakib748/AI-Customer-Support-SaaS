<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: 'unknown' }
});

const meta = {
    healthy: { label: 'Healthy', icon: 'pi pi-check-circle', tone: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-500/10', ring: 'ring-emerald-200 dark:ring-emerald-500/30' },
    degraded: { label: 'Degraded', icon: 'pi pi-exclamation-triangle', tone: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-500/10', ring: 'ring-amber-200 dark:ring-amber-500/30' },
    unhealthy: { label: 'Unhealthy', icon: 'pi pi-times-circle', tone: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-500/10', ring: 'ring-red-200 dark:ring-red-500/30' },
    unknown: { label: 'Unknown', icon: 'pi pi-question-circle', tone: 'text-surface-500 dark:text-surface-400', bg: 'bg-surface-100 dark:bg-surface-800', ring: 'ring-surface-200 dark:ring-surface-700' },
    disabled: { label: 'Disabled', icon: 'pi pi-minus-circle', tone: 'text-surface-400 dark:text-surface-500', bg: 'bg-surface-100 dark:bg-surface-800', ring: 'ring-surface-200 dark:ring-surface-700' }
};

const style = computed(() => meta[props.status] || meta.unknown);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ring-1" :class="[style.bg, style.tone, style.ring]">
        <i :class="style.icon"></i>
        {{ style.label }}
    </span>
</template>
