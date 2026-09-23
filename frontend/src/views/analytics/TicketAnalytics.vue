<!-- src/views/analytics/TicketAnalytics.vue -->
<script setup>
import { reactive, computed, onMounted, watch } from 'vue';
import { useAnalyticsStore } from '@/stores/analytics';
import MetricCard from '@/components/analytics/MetricCard.vue';
import TrendChart from '@/components/analytics/TrendChart.vue';
import StatusChart from '@/components/analytics/StatusChart.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const store = useAnalyticsStore();
const filters = reactive({ period: '30d' });
const loading = computed(() => store.loading);
const data = computed(() => store.tickets);

const load = async () => {
    store.setFilters(filters);
    await store.fetchTickets();
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Ticket Analytics</h1>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="data">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <MetricCard title="Total" :value="data.summary.total" />
                <MetricCard title="Open" :value="data.summary.open" />
                <MetricCard title="Resolved" :value="data.summary.resolved" />
                <MetricCard title="Urgent" :value="data.summary.urgent" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <TrendChart title="Tickets Created vs Resolved" :labels="data.trend.labels" :datasets="data.trend.datasets" />
                </div>
                <StatusChart title="Status" :labels="data.status_breakdown.labels" :values="data.status_breakdown.values" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <StatusChart title="Priority" :labels="data.priority_breakdown.labels" :values="data.priority_breakdown.values" />
                <StatusChart title="Type" :labels="data.type_breakdown.labels" :values="data.type_breakdown.values" />
            </div>
        </template>
    </div>
</template>
