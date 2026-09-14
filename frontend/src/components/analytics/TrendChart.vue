<!-- src/components/analytics/TrendChart.vue -->
<script setup>
import { computed } from 'vue';
import Chart from 'primevue/chart';

const props = defineProps({
    title: { type: String, default: '' },
    type: { type: String, default: 'line' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    datasets: { type: Array, default: null },
    height: { type: Number, default: 280 },
    color: { type: String, default: '#4F46E5' },
});

const chartData = computed(() => {
    if (props.datasets) {
        return {
            labels: props.labels,
            datasets: props.datasets.map((ds, i) => ({
                label: ds.label,
                data: ds.values,
                borderColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'][i % 4],
                backgroundColor: props.type === 'bar'
                    ? ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'][i % 4] + '33'
                    : undefined,
                fill: props.type === 'line' ? false : undefined,
                tension: 0.35,
            })),
        };
    }

    return {
        labels: props.labels,
        datasets: [{
            label: props.title,
            data: props.values,
            borderColor: props.color,
            backgroundColor: props.color + '22',
            fill: props.type === 'line',
            tension: 0.35,
        }],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 16 },
        },
        tooltip: { mode: 'index', intersect: false },
    },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { grid: { display: false } },
    },
};
</script>

<template>
    <Card>
        <template #title>{{ title }}</template>
        <template #content>
            <div :style="{ height: height + 'px' }">
                <Chart :type="type" :data="chartData" :options="chartOptions" />
            </div>
        </template>
    </Card>
</template>
