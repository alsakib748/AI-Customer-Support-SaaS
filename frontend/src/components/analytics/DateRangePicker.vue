<!-- src/components/analytics/DateRangePicker.vue -->
<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Object, required: true }
});
const emit = defineEmits(['update:modelValue', 'refresh']);

const customRange = ref(null);

const periodOptions = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 Days', value: '7d' },
    { label: 'Last 30 Days', value: '30d' },
    { label: 'Last 90 Days', value: '90d' },
    { label: 'This Month', value: 'this_month' },
    { label: 'Last Month', value: 'last_month' },
    { label: 'This Year', value: 'this_year' },
    { label: 'Custom Range', value: 'custom' }
];

const updatePeriod = (period) => {
    emit('update:modelValue', {
        ...props.modelValue,
        period,
        from: period === 'custom' ? props.modelValue.from : null,
        to: period === 'custom' ? props.modelValue.to : null
    });
};

const updateRange = (range) => {
    customRange.value = range;
    if (Array.isArray(range) && range[0] && range[1]) {
        const fmt = (d) => {
            const date = new Date(d);
            return date.toISOString().slice(0, 10);
        };
        emit('update:modelValue', {
            ...props.modelValue,
            period: 'custom',
            from: fmt(range[0]),
            to: fmt(range[1])
        });
    }
};

// Sync custom range when external change
watch(
    () => props.modelValue,
    (v) => {
        if (v.period !== 'custom') customRange.value = null;
    }
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <Select :modelValue="modelValue.period" :options="periodOptions" optionLabel="label" optionValue="value" class="w-48" @update:modelValue="(v) => updatePeriod(v)" />
        <Calendar v-if="modelValue.period === 'custom'" :modelValue="customRange" selectionMode="range" :manualInput="false" dateFormat="yy-mm-dd" @update:modelValue="(v) => updateRange(v)" />
        <Button icon="pi pi-refresh" label="Refresh" severity="secondary" outlined @click="$emit('refresh')" />
    </div>
</template>
