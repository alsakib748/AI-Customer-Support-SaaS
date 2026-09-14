<!-- src/views/analytics/AIAnalytics.vue -->
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
const data = computed(() => store.ai);

const load = async () => {
    store.setFilters(filters);
    await store.fetchAI();
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">AI Analytics</h1>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="data">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <MetricCard title="AI Requests" :value="data.summary.requests" />
                <MetricCard title="Resolution Rate" :value="data.summary.resolution_rate + '%'" />
                <MetricCard title="Escalation Rate" :value="data.summary.escalation_rate + '%'" />
                <MetricCard title="Total Cost" :value="'$' + data.summary.total_cost" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <TrendChart title="AI Usage Over Time" :labels="data.usage_trend.labels"
                        :datasets="data.usage_trend.datasets" />
                </div>
                <StatusChart title="Provider Distribution" :labels="data.provider_breakdown.labels"
                    :values="data.provider_breakdown.values" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card>
                    <template #title>Model Breakdown</template>
                    <template #content>
                        <DataTable :value="data.model_breakdown">
                            <Column field="model" header="Model" />
                            <Column field="requests" header="Requests" />
                            <Column field="tokens" header="Tokens" />
                            <Column field="cost" header="Cost">
                                <template #body="{ data }">${{ data.cost.toFixed(4) }}</template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>
                <Card>
                    <template #title>AI Health</template>
                    <template #content>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span>Success Rate</span>
                                <span class="font-medium text-success">
                                    {{ data.health.success_rate }}%
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Failure Rate</span>
                                <span class="font-medium text-danger">
                                    {{ data.health.failure_rate }}%
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Avg Response</span>
                                <span class="font-medium">{{ data.health.avg_response_time_ms }} ms</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Requests</span>
                                <span class="font-medium">{{ data.health.total_requests }}</span>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </template>
    </div>
</template>