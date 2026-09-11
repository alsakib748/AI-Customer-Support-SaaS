<!-- src/views/ai/AIAnalytics.vue -->
<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAIStore } from '@/stores/ai';
// import { useToast } from 'primevue/usetoast';

const aiStore = useAIStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const loading = ref(false);

// ============================================
// COMPUTED
// ============================================

const usage = computed(() => aiStore.usage);
const health = computed(() => aiStore.health);
const analytics = computed(() => aiStore.analytics);
const logs = computed(() => aiStore.logs);

const chartData = computed(() => {
    const daily = usage.value.daily_usage || [];
    return {
        labels: daily.map(d => d.date),
        datasets: [
            {
                label: 'Requests',
                data: daily.map(d => d.count),
                fill: false,
                borderColor: '#4F46E5',
                tension: 0.4,
            },
            {
                label: 'Tokens',
                data: daily.map(d => d.tokens || 0),
                fill: false,
                borderColor: '#10B981',
                tension: 0.4,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
    },
    scales: {
        y: {
            beginAtZero: true,
        },
    },
};

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            aiStore.fetchUsage(),
            aiStore.fetchHealth(),
            aiStore.fetchAnalytics(),
            aiStore.fetchLogs({ limit: 20 }),
        ]);
    } catch (error) {
        console.error('Failed to load AI analytics:', error);
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString();
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">AI Analytics</h1>
            <p class="text-surface-600 dark:text-surface-400">Monitor AI performance and usage</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ analytics.total_requests || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Requests</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">{{ (health.success_rate || 0).toFixed(1) }}%</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Success Rate</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning">{{ (health.avg_response_time || 0).toFixed(0) }}ms
                        </div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Avg Response Time</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-info">${{ (analytics.total_cost || 0).toFixed(2) }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Cost</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Usage by Provider -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <Card>
                <template #title>
                    <span class="font-semibold">Usage by Provider</span>
                </template>
                <template #content>
                    <div v-if="usage.by_provider?.length">
                        <div v-for="item in usage.by_provider" :key="item.provider"
                            class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                            <span>{{ item.provider }}</span>
                            <span class="font-medium">{{ item.count }} requests</span>
                        </div>
                    </div>
                    <div v-else class="text-center text-surface-400 py-4">
                        No data available
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>
                    <span class="font-semibold">Usage by Model</span>
                </template>
                <template #content>
                    <div v-if="usage.by_model?.length">
                        <div v-for="item in usage.by_model" :key="item.model"
                            class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                            <span>{{ item.model }}</span>
                            <span class="font-medium">{{ item.count }} requests</span>
                        </div>
                    </div>
                    <div v-else class="text-center text-surface-400 py-4">
                        No data available
                    </div>
                </template>
            </Card>
        </div>

        <!-- Daily Usage Chart -->
        <Card>
            <template #title>
                <span class="font-semibold">Daily Usage (Last 30 Days)</span>
            </template>
            <template #content>
                <div v-if="usage.daily_usage?.length" class="h-64">
                    <Chart type="line" :data="chartData" :options="chartOptions" />
                </div>
                <div v-else class="text-center text-surface-400 py-8">
                    No data available
                </div>
            </template>
        </Card>

        <!-- Logs -->
        <Card class="mt-6">
            <template #title>
                <span class="font-semibold">Recent AI Logs</span>
            </template>
            <template #content>
                <DataTable :value="logs" :loading="loading" paginator :rows="10">
                    <Column field="agent" header="Agent" />
                    <Column field="provider" header="Provider" />
                    <Column field="model" header="Model" />
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="data.status === 'success' ? 'success' : 'danger'" />
                        </template>
                    </Column>
                    <Column field="duration_ms" header="Duration">
                        <template #body="{ data }">
                            {{ data.duration_ms }}ms
                        </template>
                    </Column>
                    <Column field="created_at" header="Time">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Toast />
    </div>
</template>
