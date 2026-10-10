<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';
import { useSystemHealthStore } from '@/stores/systemHealth';
import OverallHealthCard from '@/components/admin/system-health/OverallHealthCard.vue';
import ServiceStatusTable from '@/components/admin/system-health/ServiceStatusTable.vue';
import IncidentList from '@/components/admin/system-health/IncidentList.vue';

const store = useSystemHealthStore();
const runningKey = ref(null);

const refresh = () => store.fetchSnapshot(true).catch(() => {});

const runCheck = async (check) => {
    runningKey.value = check.key;
    try {
        const result = await store.runCheck(check.key);
        toast.success(`Check "${check.label}" finished: ${result.status}`);
        await store.fetchSnapshot(true);
    } catch (err) {
        toast.error(err?.response?.data?.message || `Failed to run ${check.label}`);
    } finally {
        runningKey.value = null;
    }
};

onMounted(async () => {
    await Promise.allSettled([store.fetchSnapshot(), store.fetchIncidents({ per_page: 5, status: 'active' }), store.fetchEvents({ limit: 8 })]);
    store.startPolling(30000);
});

onBeforeUnmount(() => store.stopPolling());

const eventMeta = {
    opened: { label: 'Opened', cls: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' },
    updated: { label: 'Updated', cls: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300' },
    acknowledged: { label: 'Acknowledged', cls: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300' },
    resolved: { label: 'Resolved', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' }
};
</script>

<template>
    <div class="space-y-6">
        <OverallHealthCard :snapshot="store.snapshot" :loading="store.loading" :on-fresh="refresh" />

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div
                v-for="item in [
                    { label: 'Healthy', value: store.snapshot?.counts?.healthy ?? 0, cls: 'text-emerald-600 dark:text-emerald-400' },
                    { label: 'Degraded', value: store.snapshot?.counts?.degraded ?? 0, cls: 'text-amber-600 dark:text-amber-400' },
                    { label: 'Unhealthy', value: store.snapshot?.counts?.unhealthy ?? 0, cls: 'text-red-600 dark:text-red-400' },
                    { label: 'Unknown', value: store.snapshot?.counts?.unknown ?? 0, cls: 'text-surface-500 dark:text-surface-400' },
                    { label: 'Active incidents', value: store.snapshot?.active_incidents ?? 0, cls: 'text-red-600 dark:text-red-400' }
                ]"
                :key="item.label"
                class="rounded-xl bg-surface-50 dark:bg-surface-800 p-4 text-center"
            >
                <div class="text-2xl font-bold" :class="item.cls">{{ item.value }}</div>
                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ item.label }}</div>
            </div>
        </div>

        <ServiceStatusTable :checks="store.checks" :loading="store.loading" :on-run="runCheck" :running-key="runningKey" />

        <div class="grid lg:grid-cols-2 gap-6">
            <IncidentList :incidents="store.incidents" :loading="store.loading" />

            <Card>
                <template #title>Recent events</template>
                <template #content>
                    <div v-if="!store.events.length" class="text-surface-500 dark:text-surface-400 text-sm py-8 text-center">No recent operational events.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="ev in store.events" :key="ev.id" class="flex items-start gap-3 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full mt-0.5 shrink-0" :class="(eventMeta[ev.event] || eventMeta.updated).cls">
                                {{ (eventMeta[ev.event] || eventMeta.updated).label }}
                            </span>
                            <div class="min-w-0">
                                <RouterLink class="font-medium text-surface-800 dark:text-surface-100 hover:text-primary block truncate" :to="`/admin/system-health/incidents/${ev.incident_id}`">
                                    {{ ev.incident_title }}
                                </RouterLink>
                                <div class="text-xs text-surface-500 dark:text-surface-400">
                                    {{ new Date(ev.created_at).toLocaleString() }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </template>
            </Card>
        </div>
    </div>
</template>
