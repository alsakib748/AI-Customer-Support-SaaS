<script setup>
import StatusBadge from '@/components/admin/system-health/StatusBadge.vue';

defineProps({
    checks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    onRun: { type: Function, default: null },
    runningKey: { type: [String, null], default: null }
});

const emit = defineEmits(['row-click']);

const formatTime = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
</script>

<template>
    <Card>
        <template #title>
            <div class="flex items-center justify-between">
                <span>Services</span>
                <span class="text-xs font-normal text-surface-500 dark:text-surface-400"> {{ checks.length }} configured · latency & outcomes from last run </span>
            </div>
        </template>
        <template #content>
            <div v-if="loading && !checks.length" class="text-surface-500 dark:text-surface-400 text-sm py-8 text-center">Loading checks…</div>

            <div v-else-if="!checks.length" class="text-surface-500 dark:text-surface-400 text-sm py-8 text-center">No checks configured yet.</div>

            <ul v-else class="divide-y divide-surface-200 dark:divide-surface-700">
                <li v-for="check in checks" :key="check.key" class="py-4 flex items-center gap-4 flex-wrap cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/50 rounded-lg px-2" @click="emit('row-click', check)">
                    <div class="flex-1 min-w-40">
                        <div class="font-semibold text-surface-900 dark:text-surface-0">
                            {{ check.label }}
                            <i v-if="check.critical" v-tooltip="'Critical check'" class="pi pi-exclamation-circle text-xs text-amber-500"></i>
                        </div>
                        <div class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                            <code class="font-mono">{{ check.key }}</code>
                            <span v-if="check.duration_ms"> · {{ check.duration_ms }}ms</span>
                            <span v-if="check.stale" class="text-amber-500"> · stale</span>
                        </div>
                    </div>

                    <div class="text-sm text-surface-600 dark:text-surface-300 max-w-md flex-1">
                        {{ check.summary || '' }}
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <StatusBadge :status="check.status" />
                            <div class="text-xs text-surface-400 dark:text-surface-500 mt-1">
                                {{ formatTime(check.checked_at) }}
                            </div>
                        </div>

                        <Button v-if="onRun && check.status !== 'disabled'" icon="pi pi-play" :loading="runningKey === check.key" severity="secondary" text rounded size="small" v-tooltip="'Run now'" @click.stop="onRun(check)" />
                    </div>
                </li>
            </ul>
        </template>
    </Card>
</template>
