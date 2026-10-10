<script setup>
import 'chart.js/auto';
import { computed, onMounted, ref, watch } from 'vue';
import { useSystemHealthStore } from '@/stores/systemHealth';

const store = useSystemHealthStore();

const ranges = [
    { label: 'Last 6 hours', hours: 6 },
    { label: 'Last 24 hours', hours: 24 },
    { label: 'Last 7 days', hours: 168 }
];

const metric = ref('check.duration_ms');
const source = ref(null);
const range = ref(ranges[1].hours);

const chartData = ref(null);

const options = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: { beginAtZero: true },
        x: { ticks: { maxTicksLimit: 10 } }
    }
};

const stats = computed(() => store.metrics);

const uptime = computed(() => {
    const backend = stats.value?.availability;
    if (backend && backend.total > 0) {
        return {
            available: backend.available,
            total: backend.total,
            pct: backend.pct
        };
    }

    if (!stats.value?.samples?.length) return null;
    const healthy = stats.value.samples.filter((s) => s.value >= 1).length;
    return {
        available: healthy,
        total: stats.value.samples.length,
        pct: Math.round((healthy / stats.value.samples.length) * 1000) / 10
    };
});

const load = async () => {
    const params = {
        metric: metric.value,
        limit: 300
    };
    if (source.value) params.source = source.value;

    const to = new Date();
    const from = new Date(to.getTime() - range.value * 3600 * 1000);
    params.from = from.toISOString();
    params.to = to.toISOString();

    const data = await store.fetchMetrics(params);

    const labels = data.samples.map((s) => new Date(s.recorded_at).toLocaleTimeString());
    const values = data.samples.map((s) => s.value);

    chartData.value = {
        labels,
        datasets: [
            {
                label: metric.value,
                data: values,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.15)',
                fill: true,
                tension: 0.3,
                pointRadius: 1
            }
        ]
    };
};

watch([metric, source, range], load);

const sources = computed(() => store.checks.map((c) => ({ label: c.label, value: c.key })));

onMounted(() => {
    load().catch(() => {});
});
</script>

<template>
    <div class="space-y-6">
        <Card>
            <template #content>
                <div class="grid md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-surface-500 dark:text-surface-400 mb-1 uppercase tracking-wider">Metric</label>
                        <Select v-model="metric" :options="['check.duration_ms', 'check.status']" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs text-surface-500 dark:text-surface-400 mb-1 uppercase tracking-wider">Source</label>
                        <Select v-model="source" :options="sources" option-label="label" option-value="value" show-clear placeholder="All checks" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs text-surface-500 dark:text-surface-400 mb-1 uppercase tracking-wider">Range</label>
                        <Select v-model="range" :options="ranges" option-label="label" option-value="hours" class="w-full" />
                    </div>
                </div>
            </template>
        </Card>

        <div class="grid md:grid-cols-3 gap-4">
            <div class="rounded-xl bg-surface-50 dark:bg-surface-800 p-4 text-center">
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ stats?.count ?? 0 }}</div>
                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">Samples</div>
            </div>
            <div class="rounded-xl bg-surface-50 dark:bg-surface-800 p-4 text-center">
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ stats?.min ?? '—' }}</div>
                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">Min {{ metric === 'check.status' ? '(0-1)' : 'ms' }}</div>
            </div>
            <div class="rounded-xl bg-surface-50 dark:bg-surface-800 p-4 text-center">
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ stats?.avg != null ? Number(stats.avg).toFixed(1) : '—' }}</div>
                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">Average</div>
            </div>
        </div>

        <div v-if="uptime && metric === 'check.status'" class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-6 text-center ring-1 ring-emerald-200 dark:ring-emerald-500/30">
            <div class="text-4xl font-bold text-emerald-700 dark:text-emerald-300">{{ uptime.pct }}%</div>
            <div class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">Available in {{ uptime.available }} of {{ uptime.total }} samples</div>
        </div>

        <Card v-if="chartData">
            <template #content>
                <div class="h-80">
                    <Chart type="line" :data="chartData" :options="options" />
                </div>
            </template>
        </Card>

        <div v-else class="text-surface-400 dark:text-surface-500 text-sm py-10 text-center">No metric samples available yet.</div>
    </div>
</template>
