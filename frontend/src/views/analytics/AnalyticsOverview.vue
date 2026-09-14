<!-- src/views/analytics/AnalyticsOverview.vue -->
<script setup>
import { reactive, computed, onMounted, watch } from 'vue';
import { useAnalyticsStore } from '@/stores/analytics';
import MetricCard from '@/components/analytics/MetricCard.vue';
import TrendChart from '@/components/analytics/TrendChart.vue';
import StatusChart from '@/components/analytics/StatusChart.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const store = useAnalyticsStore();

const filters = reactive({
    period: '30d',
    from: null,
    to: null,
});

const loading = computed(() => store.loading);
const overview = computed(() => store.overview);

const load = async () => {
    store.setFilters(filters);
    await store.fetchOverview();
};

const refresh = () => load();

onMounted(load);

watch(() => [filters.period, filters.from, filters.to], () => {
    load();
});

</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Analytics</h1>
                <p class="text-surface-600 dark:text-surface-400">
                    Workspace performance overview
                </p>
            </div>
            <DateRangePicker v-model="filters" @refresh="refresh" />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="overview">
            <!-- Row 1: Primary KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <MetricCard title="Total Conversations" :value="overview.summary.total_conversations.current"
                    :change="overview.summary.total_conversations.change_percentage"
                    :is-positive="overview.summary.total_conversations.is_positive" />
                <MetricCard title="Open Conversations" :value="overview.summary.open_conversations.value" />
                <MetricCard title="Resolved Conversations" :value="overview.summary.resolved_conversations.current"
                    :change="overview.summary.resolved_conversations.change_percentage"
                    :is-positive="overview.summary.resolved_conversations.is_positive" />
                <MetricCard title="AI Resolution Rate" :value="overview.summary.ai_resolution_rate.current + '%'"
                    :change="overview.summary.ai_resolution_rate.change_percentage"
                    :is-positive="overview.summary.ai_resolution_rate.is_positive" />
            </div>

            <!-- Row 2: Secondary KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <MetricCard title="New Customers" :value="overview.summary.new_customers.current"
                    :change="overview.summary.new_customers.change_percentage"
                    :is-positive="overview.summary.new_customers.is_positive" />
                <MetricCard title="Open Tickets" :value="overview.summary.open_tickets.value" />
                <MetricCard title="Avg Response Time" :value="overview.summary.formatted.avg_response_time" />
                <MetricCard title="Avg Resolution Time" :value="overview.summary.formatted.avg_resolution_time" />
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <div class="lg:col-span-2">
                    <TrendChart title="Conversation Volume" :labels="overview.conversation_trend.labels"
                        :values="overview.conversation_trend.values" />
                </div>
                <StatusChart title="Conversation Status" :labels="overview.status_breakdown.labels"
                    :values="overview.status_breakdown.values" />
            </div>

            <!-- Bottom Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <TrendChart title="Customer Growth" :labels="overview.customer_trend.labels"
                    :values="overview.customer_trend.values" color="#10B981" />
                <Card>
                    <template #title>AI Summary</template>
                    <template #content>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-surface-500">AI Requests</div>
                                <div class="text-xl font-bold">
                                    {{ overview.ai_summary.requests.toLocaleString() }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-surface-500">AI Resolved</div>
                                <div class="text-xl font-bold text-success">
                                    {{ overview.ai_summary.resolved.toLocaleString() }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-surface-500">Escalated</div>
                                <div class="text-xl font-bold text-warning">
                                    {{ overview.ai_summary.escalated.toLocaleString() }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-surface-500">Tokens Used</div>
                                <div class="text-xl font-bold">
                                    {{ overview.ai_summary.tokens.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </template>

        <Toast />
    </div>
</template>
