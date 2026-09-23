<!-- src/views/analytics/AgentAnalytics.vue -->
<script setup>
import { reactive, ref, computed, onMounted, watch, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';
import { useAnalyticsStore } from '@/stores/analytics';
import MetricCard from '@/components/analytics/MetricCard.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const store = useAnalyticsStore();
const filters = reactive({ period: '30d' });
const loading = computed(() => store.loading);
const data = computed(() => store.agents);
const workloadCanvas = ref(null);
let workloadChart = null;

const workloadData = computed(() => ({
    labels: data.value?.workload?.labels || [],
    datasets: [
        {
            label: 'Open Conversations',
            data: data.value?.workload?.values || [],
            backgroundColor: '#4F46E5',
            borderRadius: 6
        }
    ]
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
};

const load = async () => {
    store.setFilters(filters);
    await store.fetchAgents();
};

function ensureChart() {
    if (workloadChart || !workloadCanvas.value) return;
    workloadChart = new Chart(workloadCanvas.value, {
        type: 'bar',
        data: workloadData.value,
        options: barOptions
    });
}

function destroyChart() {
    if (workloadChart) {
        workloadChart.destroy();
        workloadChart = null;
    }
}

onMounted(() => {
    ensureChart();
    load();
});

onBeforeUnmount(destroyChart);

watch(
    workloadData,
    (newData) => {
        ensureChart();
        if (!workloadChart) return;
        workloadChart.data = newData;
        workloadChart.update();
    },
    { immediate: true }
);

watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Team Analytics</h1>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <MetricCard title="Active Agents" :value="data.summary.active_agents" />
                <MetricCard title="Unassigned Conversations" :value="data.summary.unassigned_conversations" />
            </div>

            <Card class="mb-4">
                <template #title>Agent Performance</template>
                <template #content>
                    <DataTable :value="data.performance_table" :paginator="true" :rows="10">
                        <Column field="agent_name" header="Agent" />
                        <Column field="assigned" header="Assigned" />
                        <Column field="resolved" header="Resolved" />
                        <Column field="open" header="Open" />
                        <Column field="resolution_rate" header="Resolution %">
                            <template #body="{ data }">{{ data.resolution_rate }}%</template>
                        </Column>
                        <Column field="avg_resolution_formatted" header="Avg Resolution" />
                    </DataTable>
                </template>
            </Card>

            <Card>
                <template #title>Agent Workload (Current Open)</template>
                <template #content>
                    <div style="height: 320px">
                        <canvas ref="workloadCanvas"></canvas>
                    </div>
                </template>
            </Card>
        </template>
    </div>
</template>
