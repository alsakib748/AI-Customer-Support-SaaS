<!-- src/views/analytics/MyAnalytics.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import analyticsService from '@/services/analyticsService';
import MetricCard from '@/components/analytics/MetricCard.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const filters = reactive({ period: '30d', from: null, to: null });
const loading = ref(false);
const data = ref(null);

const row = computed(() => data.value?.performance_table?.[0] ?? null);

const load = async () => {
    loading.value = true;
    try {
        const response = await analyticsService.getAgents({ ...filters });
        if (response.data.success) {
            data.value = response.data.data;
        }
    } finally {
        loading.value = false;
    }
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">My Analytics</h1>
                <p class="text-surface-600 dark:text-surface-400">Your personal performance metrics</p>
            </div>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="row">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <MetricCard title="Assigned" :value="row.assigned" />
                <MetricCard title="Resolved" :value="row.resolved" />
                <MetricCard title="Open" :value="row.open" />
                <MetricCard title="Resolution Rate" :value="row.resolution_rate + '%'" />
            </div>

            <Card>
                <template #title>Average Resolution Time</template>
                <template #content>
                    <div class="text-3xl font-bold">
                        {{ row.avg_resolution_formatted }}
                    </div>
                </template>
            </Card>
        </template>

        <div v-else class="text-center py-12 text-surface-500">No data available for the selected period.</div>
    </div>
</template>
