<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    title: { type: String, default: '' },
    type: { type: String, default: 'line' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    datasets: { type: Array, default: null },
    height: { type: Number, default: 280 },
    color: { type: String, default: '#4F46E5' }
});

const canvas = ref(null);
let chart = null;

const palette = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'];

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 16 }
        },
        tooltip: { mode: 'index', intersect: false }
    },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { grid: { display: false } }
    }
};

function buildData() {
    if (props.datasets) {
        return {
            labels: props.labels,
            datasets: props.datasets.map((ds, i) => ({
                label: ds.label,
                data: ds.values,
                borderColor: palette[i % palette.length],
                backgroundColor: props.type === 'bar' ? palette[i % palette.length] + '33' : undefined,
                fill: props.type === 'line' ? false : undefined,
                tension: 0.35
            }))
        };
    }

    return {
        labels: props.labels,
        datasets: [
            {
                label: props.title,
                data: props.values,
                borderColor: props.color,
                backgroundColor: props.color + '22',
                fill: props.type === 'line',
                tension: 0.35
            }
        ]
    };
}

function create() {
    if (!canvas.value) return;
    chart = new Chart(canvas.value, {
        type: props.type,
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

function reinit() {
    destroy();
    create();
}

onMounted(create);
onBeforeUnmount(destroy);

watch(() => [props.labels, props.values, props.datasets], update, { deep: true });
watch(() => props.type, reinit);
</script>

<template>
    <Card>
        <template #title>{{ title }}</template>
        <template #content>
            <div :style="{ height: height + 'px' }">
                <canvas ref="canvas"></canvas>
            </div>
        </template>
    </Card>
</template>
