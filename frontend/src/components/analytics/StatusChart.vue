<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    title: { type: String, default: '' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] }
});

const canvas = ref(null);
let chart = null;

const palette = ['#4F46E5', '#F59E0B', '#10B981', '#EF4444', '#8B5CF6', '#06B6D4'];

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 16 }
        }
    }
};

function buildData() {
    return {
        labels: props.labels,
        datasets: [
            {
                data: props.values,
                backgroundColor: palette,
                hoverBackgroundColor: palette,
                borderWidth: 0
            }
        ]
    };
}

function create() {
    if (!canvas.value) return;
    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: buildData(),
        options: chartOptions
    });
}

function destroy() {
    if (chart) {
        chart.destroy();
        chart = null;
    }
}

function update() {
    if (!chart) return;
    chart.data = buildData();
    chart.update();
}

onMounted(create);
onBeforeUnmount(destroy);

watch(() => [props.labels, props.values], update, { deep: true });
</script>

<template>
    <Card>
        <template #title>{{ title }}</template>
        <template #content>
            <div style="height: 280px">
                <canvas ref="canvas"></canvas>
            </div>
        </template>
    </Card>
</template>
