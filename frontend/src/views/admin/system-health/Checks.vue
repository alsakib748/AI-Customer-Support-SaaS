<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';
import { useSystemHealthStore } from '@/stores/systemHealth';
import ServiceStatusTable from '@/components/admin/system-health/ServiceStatusTable.vue';
import StatusBadge from '@/components/admin/system-health/StatusBadge.vue';

const store = useSystemHealthStore();
const selected = ref(null);
const runningKey = ref(null);

const runCheck = async (check) => {
    runningKey.value = check.key;
    try {
        const result = await store.runCheck(check.key);
        toast.success(`Check "${check.label}" finished: ${result.status}`);
        await store.fetchSnapshot(true);
        await store.fetchCheck(check.key);
    } catch (err) {
        toast.error(err?.response?.data?.message || `Failed to run ${check.label}`);
    } finally {
        runningKey.value = null;
    }
};

const select = async (check) => {
    selected.value = check;
    await store.fetchCheck(check.key);
};

const maxDuration = (history) => Math.max(1, ...(history || []).map((h) => h.duration_ms || 0));

const barWidth = (h, max) => Math.max(4, Math.round(((h.duration_ms || 0) / max) * 100));

onMounted(async () => {
    await store.fetchSnapshot();
    const bad = store.checks.filter((c) => ['unhealthy', 'degraded'].includes(c.status));
    if (bad.length) {
        await select(bad[0]);
    }
    store.startPolling(60000);
});

onBeforeUnmount(() => store.stopPolling());
</script>

<template>
    <div class="grid lg:grid-cols-5 gap-6 items-start">
        <div class="lg:col-span-3">
            <ServiceStatusTable :checks="store.checks" :loading="store.loading" :on-run="runCheck" :running-key="runningKey" @row-click="select" />

            <p class="text-xs text-surface-400 dark:text-surface-500 mt-3">Select a check to inspect its recent history. "Run now" executes a live, rate-limited probe.</p>
        </div>

        <div class="lg:col-span-2">
            <template v-if="selected && store.checkDetail">
                <Card>
                    <template #title>
                        <div class="flex items-center justify-between gap-2">
                            <span>{{ selected.label }}</span>
                            <StatusBadge :status="store.checkDetail.latest?.status || selected.status" />
                        </div>
                    </template>
                    <template #subtitle>
                        <code class="font-mono text-xs">{{ selected.key }}</code>
                    </template>
                    <template #content>
                        <div class="text-sm text-surface-600 dark:text-surface-300 mb-4">
                            {{ store.checkDetail.latest?.summary || selected.summary || 'No summary.' }}
                        </div>

                        <div class="mb-2 text-xs text-surface-500 dark:text-surface-400">Duration (last {{ store.checkDetail.history?.length || 0 }} runs)</div>
                        <div v-if="store.checkDetail.history?.length" class="flex items-end gap-1 h-16">
                            <div
                                v-for="(h, i) in [...store.checkDetail.history].reverse()"
                                :key="i"
                                class="flex-1 rounded-t"
                                :title="`${h.status} · ${h.duration_ms}ms · ${new Date(h.checked_at).toLocaleString()}`"
                                :class="
                                    h.status === 'healthy'
                                        ? 'bg-emerald-400 dark:bg-emerald-500'
                                        : h.status === 'degraded'
                                          ? 'bg-amber-400 dark:bg-amber-500'
                                          : h.status === 'unhealthy'
                                            ? 'bg-red-400 dark:bg-red-500'
                                            : 'bg-surface-300 dark:bg-surface-600'
                                "
                                :style="{ height: `${barWidth(h, maxDuration(store.checkDetail.history))}%` }"
                            ></div>
                        </div>
                        <div v-else class="text-xs text-surface-400 dark:text-surface-500">No history recorded for this check yet.</div>

                        <div v-if="store.checkDetail.latest?.metadata" class="mt-4 border-t border-surface-200 dark:border-surface-700 pt-3">
                            <div class="text-xs text-surface-500 dark:text-surface-400 mb-2">Metadata</div>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <template v-for="(value, key) in store.checkDetail.latest.metadata" :key="key">
                                    <dt class="text-surface-500 dark:text-surface-400 font-mono">{{ key }}</dt>
                                    <dd class="text-surface-800 dark:text-surface-100 font-mono break-all">
                                        {{ String(value) }}
                                    </dd>
                                </template>
                            </dl>
                        </div>
                    </template>
                </Card>
            </template>

            <div v-else class="text-surface-400 dark:text-surface-500 text-sm py-10 text-center">Select a check to see its history and metadata.</div>
        </div>
    </div>
</template>
