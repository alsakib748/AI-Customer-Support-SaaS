<!-- src/views/analytics/KnowledgeBaseAnalytics.vue -->
<script setup>
import { reactive, computed, onMounted, watch } from 'vue';
import { useAnalyticsStore } from '@/stores/analytics';
import MetricCard from '@/components/analytics/MetricCard.vue';
import StatusChart from '@/components/analytics/StatusChart.vue';
import DateRangePicker from '@/components/analytics/DateRangePicker.vue';

const store = useAnalyticsStore();
const filters = reactive({ period: '30d' });
const loading = computed(() => store.loading);
const data = computed(() => store.knowledgeBase);

const load = async () => {
    store.setFilters(filters);
    await store.fetchKnowledgeBase();
};

onMounted(load);
watch(() => [filters.period, filters.from, filters.to], load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Knowledge Base Analytics</h1>
            <DateRangePicker v-model="filters" @refresh="load" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="data">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <MetricCard title="Total Articles" :value="data.summary.total_articles" />
                <MetricCard title="Published" :value="data.summary.published" />
                <MetricCard title="AI Eligible" :value="data.summary.ai_eligible" />
                <MetricCard title="Categories" :value="data.summary.total_categories" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <StatusChart title="Article Status" :labels="data.status_breakdown.labels"
                    :values="data.status_breakdown.values" />
                <StatusChart title="Articles per Category" :labels="data.category_breakdown.labels"
                    :values="data.category_breakdown.values" />
            </div>
        </template>
    </div>
</template>
