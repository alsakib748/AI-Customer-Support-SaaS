<script setup>
import { computed } from 'vue';

const props = defineProps({
    snapshot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    onFresh: { type: Function, default: null }
});

const overall = computed(() => {
    const status = props.snapshot?.overall || 'unknown';
    const palette = {
        healthy: { icon: 'pi pi-check-circle', ring: 'ring-emerald-300', text: 'text-emerald-700 dark:text-emerald-300', bg: 'from-emerald-50 to-emerald-100 dark:from-emerald-500/10 dark:to-emerald-500/5' },
        degraded: { icon: 'pi pi-exclamation-triangle', ring: 'ring-amber-300', text: 'text-amber-700 dark:text-amber-300', bg: 'from-amber-50 to-amber-100 dark:from-amber-500/10 dark:to-amber-500/5' },
        unhealthy: { icon: 'pi pi-times-circle', ring: 'ring-red-300', text: 'text-red-700 dark:text-red-300', bg: 'from-red-50 to-red-100 dark:from-red-500/10 dark:to-red-500/5' },
        unknown: { icon: 'pi pi-question-circle', ring: 'ring-surface-300', text: 'text-surface-700 dark:text-surface-300', bg: 'from-surface-50 to-surface-100 dark:from-surface-800 dark:to-surface-800' }
    };
    return palette[status] || palette.unknown;
});

const label = computed(() => {
    const map = {
        healthy: 'All systems operational',
        degraded: 'Performance degraded',
        unhealthy: 'Critical systems affected',
        unknown: 'Availability unknown'
    };
    return map[props.snapshot?.overall] || 'No data';
});
</script>

<template>
    <Card>
        <template #content>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4 rounded-2xl px-6 py-5 ring-1 bg-gradient-to-br" :class="[overall.bg, overall.ring]">
                    <i :class="[overall.icon, overall.text]" class="text-4xl"></i>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-surface-500 dark:text-surface-400">Overall status</div>
                        <div class="text-2xl font-bold capitalize" :class="overall.text">{{ snapshot?.overall || 'unknown' }}</div>
                    </div>
                </div>

                <div class="text-sm text-surface-600 dark:text-surface-400 flex-1 min-w-52">
                    {{ label }}
                    <div v-if="snapshot?.active_incidents" class="mt-1 text-red-600 dark:text-red-400 font-medium">{{ snapshot.active_incidents }} active incident{{ snapshot.active_incidents === 1 ? '' : 's' }}</div>
                    <div class="mt-1 text-surface-400 dark:text-surface-500 text-xs">Generated {{ snapshot?.generated_at ? new Date(snapshot.generated_at).toLocaleString() : '—' }}</div>
                </div>

                <Button label="Refresh" icon="pi pi-refresh" :loading="loading" size="small" text @click="onFresh?.()" />
            </div>
        </template>
    </Card>
</template>
