<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    incidents: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

const severityTone = {
    critical: { badge: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300', dot: 'bg-red-500' },
    error: { badge: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300', dot: 'bg-orange-500' },
    warning: { badge: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300', dot: 'bg-amber-500' },
    info: { badge: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300', dot: 'bg-sky-500' }
};

const statusLabel = {
    open: 'Open',
    acknowledged: 'Acknowledged',
    resolved: 'Resolved'
};

const tone = (severity) => severityTone[severity] || severityTone.info;
</script>

<template>
    <Card>
        <template #title>
            <span>Incidents</span>
        </template>
        <template #content>
            <div v-if="loading && !incidents.length" class="text-surface-500 dark:text-surface-400 text-sm py-8 text-center">Loading incidents…</div>

            <div v-else-if="!incidents.length" class="text-surface-500 dark:text-surface-400 text-sm py-8 text-center">No incidents recorded.</div>

            <ul v-else class="space-y-3">
                <li v-for="incident in incidents" :key="incident.id" class="rounded-lg border border-surface-200 dark:border-surface-700 p-4 flex items-start gap-3">
                    <span class="mt-1.5 h-2.5 w-2.5 rounded-full shrink-0" :class="tone(incident.severity).dot"></span>
                    <div class="flex-1 min-w-0">
                        <RouterLink :to="`/admin/system-health/incidents/${incident.id}`" class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary">
                            {{ incident.title }}
                        </RouterLink>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                            <span class="font-mono">{{ incident.fingerprint }}</span>
                            · started {{ new Date(incident.started_at).toLocaleString() }}
                        </p>
                        <p v-if="incident.summary" class="text-sm text-surface-600 dark:text-surface-300 mt-1 line-clamp-2">{{ incident.summary }}</p>
                        <div class="flex gap-2 mt-2 flex-wrap">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="tone(incident.severity).badge">
                                {{ incident.severity }}
                            </span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                {{ statusLabel[incident.status] || incident.status }}
                            </span>
                            <span class="text-xs text-surface-400 dark:text-surface-500 self-center">{{ incident.source }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </Card>
</template>
