<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useSystemHealthStore } from '@/stores/systemHealth';
import IncidentTimeline from '@/components/admin/system-health/IncidentTimeline.vue';

const route = useRoute();
const store = useSystemHealthStore();
const error = ref(null);

const incident = () => store.incident;

const statusBadge = {
    open: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
    acknowledged: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
};

const ack = async () => {
    try {
        await store.acknowledgeIncident(incident().id);
        toast.success('Incident acknowledged');
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Failed to acknowledge incident');
    }
};

const resolve = async () => {
    try {
        await store.resolveIncident(incident().id);
        toast.success('Incident resolved');
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Failed to resolve incident');
    }
};

onMounted(async () => {
    try {
        await store.fetchIncident(route.params.id);
    } catch (err) {
        error.value = err?.response?.data?.message || 'Incident not found';
    }
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="error" class="text-red-600 dark:text-red-400">
            {{ error }}
        </div>

        <template v-else-if="incident()">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <RouterLink :to="'/admin/system-health'" class="text-sm text-surface-500 dark:text-surface-400 hover:text-primary"> &larr; Back to dashboard </RouterLink>
                    <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mt-2">{{ incident().title }}</h1>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1 font-mono">{{ incident().fingerprint }}</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="statusBadge[incident().status] || 'bg-surface-100 text-surface-600'">
                        {{ incident().status_label }}
                    </span>
                    <Button label="Acknowledge" icon="pi pi-check" severity="secondary" :loading="store.managing" :disabled="incident().status !== 'open'" size="small" @click="ack" />
                    <Button label="Resolve" icon="pi pi-check-circle" :loading="store.managing" :disabled="incident().status === 'resolved'" size="small" @click="resolve" />
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <Card>
                        <template #title>Details</template>
                        <template #content>
                            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Status</dt>
                                    <dd class="font-medium mt-1">{{ incident().status_label }}</dd>
                                </div>
                                <div>
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Severity</dt>
                                    <dd class="font-medium mt-1">{{ incident().severity_label }}</dd>
                                </div>
                                <div>
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Source</dt>
                                    <dd class="font-medium mt-1">{{ incident().source }}</dd>
                                </div>
                                <div>
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Started</dt>
                                    <dd class="font-medium mt-1">{{ new Date(incident().started_at).toLocaleString() }}</dd>
                                </div>
                                <div v-if="incident().acknowledged_at">
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Acknowledged</dt>
                                    <dd class="font-medium mt-1">{{ new Date(incident().acknowledged_at).toLocaleString() }}</dd>
                                </div>
                                <div v-if="incident().resolved_at">
                                    <dt class="text-surface-500 dark:text-surface-400 text-xs uppercase tracking-wider">Resolved</dt>
                                    <dd class="font-medium mt-1">{{ new Date(incident().resolved_at).toLocaleString() }}</dd>
                                </div>
                            </dl>

                            <p v-if="incident().summary" class="mt-5 text-surface-700 dark:text-surface-200 border-t border-surface-200 dark:border-surface-700 pt-4">
                                {{ incident().summary }}
                            </p>
                        </template>
                    </Card>
                </div>

                <div>
                    <Card>
                        <template #title>Timeline</template>
                        <template #content>
                            <IncidentTimeline :events="incident().events || []" />
                        </template>
                    </Card>
                </div>
            </div>
        </template>

        <div v-else class="text-surface-500 dark:text-surface-400 text-sm py-10 text-center">Loading incident…</div>
    </div>
</template>
