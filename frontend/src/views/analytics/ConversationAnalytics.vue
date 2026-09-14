<!-- src/views/analytics/ConversationAnalytics.vue -->
<script setup>
import { reactive, ref, computed, onMounted, watch } from 'vue';
import { useAnalyticsStore } from '@/stores/analytics';
import MetricCard from '@/components/analytics/MetricCard.vue';
import TrendChart from '@/components/analytics/TrendChart.vue';
import StatusChart from '@/components/analytics/StatusChart.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const store = useAnalyticsStore();
const filters = reactive({ period: '30d', from: null, to: null });
const loading = computed(() => store.loading);
const data = computed(() => store.conversations);

const load = async () => {
    store.setFilters(filters);
    await store.fetchConversations();
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Conversation Analytics</h1>
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
                <MetricCard title="Unassigned" :value="data.summary.unassigned" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <TrendChart title="Created vs Resolved" :labels="data.trend.labels"
                        :datasets="data.trend.datasets" />
                </div>
                <StatusChart title="Priority" :labels="data.priority_breakdown.labels"
                    :values="data.priority_breakdown.values" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <StatusChart title="Channel" :labels="data.channel_breakdown.labels"
                    :values="data.channel_breakdown.values" />
                <Card>
                    <template #title>Response Time</template>
                    <template #content>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Average</span>
                                <span class="font-medium">{{ data.response_time.average_formatted }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Median</span>
                                <span class="font-medium">{{ Math.round(data.response_time.median_seconds) }}s</span>
                            </div>
                            <div class="flex justify-between">
                                <span>P90</span>
                                <span class="font-medium">{{ Math.round(data.response_time.p90_seconds) }}s</span>
                            </div>
                        </div>
                    </template>
                </Card>
                <Card>
                    <template #title>Resolution Time</template>
                    <template #content>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Average</span>
                                <span class="font-medium">{{ data.resolution_time.average_formatted }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Median</span>
                                <span class="font-medium">{{ Math.round(data.resolution_time.median_seconds) }}s</span>
                            </div>
                            <div class="flex justify-between">
                                <span>P90</span>
                                <span class="font-medium">{{ Math.round(data.resolution_time.p90_seconds) }}s</span>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </template>
    </div>
</template>
