<!-- src/views/analytics/WidgetAnalytics.vue -->
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
const data = computed(() => store.widget);

const load = async () => {
    store.setFilters(filters);
    await store.fetchWidget();
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Widget Analytics</h1>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="data">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <MetricCard title="Sessions" :value="data.summary.sessions" />
                <MetricCard title="Unique Visitors" :value="data.summary.unique_visitors" />
                <MetricCard title="Conversations" :value="data.summary.conversations" />
                <MetricCard title="Conversion Rate" :value="data.summary.conversion_rate + '%'" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <TrendChart title="Sessions & Conversations" :labels="data.trend.labels" :datasets="data.trend.datasets" />
                <StatusChart title="Sessions by Widget" :labels="data.by_widget.map((w) => w.widget_name)" :values="data.by_widget.map((w) => w.sessions)" />
            </div>

            <Card>
                <template #title>Per Widget Performance</template>
                <template #content>
                    <DataTable :value="data.by_widget">
                        <Column field="widget_name" header="Widget" />
                        <Column field="sessions" header="Sessions" />
                        <Column field="visitors" header="Visitors" />
                        <Column field="conversations" header="Conversations" />
                        <Column field="conversion_rate" header="Conversion %">
                            <template #body="{ data }">{{ data.conversion_rate }}%</template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </template>
    </div>
</template>
