<!-- src/components/analytics/StatusChart.vue -->
<script setup>
import { computed } from 'vue';
import Chart from 'primevue/chart';

const props = defineProps({
    title: { type: String, default: '' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] }
});

const palette = ['#4F46E5', '#F59E0B', '#10B981', '#EF4444', '#8B5CF6', '#06B6D4'];

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            data: props.values,
            backgroundColor: palette,
            hoverBackgroundColor: palette,
            borderWidth: 0
        }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } }
    }
};
</script>

<template>
    <Card>
        <template #title>{{ title }}</template>
        <template #content>
            <div style="height: 280px">
                <Chart type="doughnut" :data="chartData" :options="chartOptions" />
            </div>
        </template>
    </Card>
</template>
