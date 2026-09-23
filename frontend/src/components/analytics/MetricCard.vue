<!-- src/components/analytics/MetricCard.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [Number, String], default: 0 },
    change: { type: Number, default: null },
    isPositive: { type: Boolean, default: null },
    suffix: { type: String, default: '' }
});

const formattedValue = computed(() => {
    if (typeof props.value === 'number') {
        return props.value.toLocaleString() + props.suffix;
    }
    return props.value;
});

const changeClass = computed(() => {
    if (props.isPositive === null) {
        return props.change >= 0 ? 'text-success' : 'text-danger';
    }
    return props.isPositive ? 'text-success' : 'text-danger';
});

const changeIcon = computed(() => {
    return props.change >= 0 ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-right';
});
</script>

<template>
    <Card class="h-full">
        <template #content>
            <div class="flex flex-col justify-between h-full">
                <div class="text-sm text-surface-500 dark:text-surface-400 mb-2">
                    {{ title }}
                </div>
                <div class="flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                        {{ formattedValue }}
                    </div>
                    <div v-if="change !== null && change !== undefined" class="flex items-center gap-1 text-sm font-medium" :class="changeClass">
                        <i :class="changeIcon"></i>
                        <span>{{ Math.abs(change).toFixed(1) }}%</span>
                    </div>
                </div>
                <div v-if="change !== null && change !== undefined" class="text-xs text-surface-400 mt-1">vs previous period</div>
            </div>
        </template>
    </Card>
</template>
