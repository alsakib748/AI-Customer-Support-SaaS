<script setup>
defineProps({
    events: { type: Array, default: () => [] }
});

const meta = {
    opened: { label: 'Opened', icon: 'pi pi-exclamation-triangle', cls: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300', ring: 'border-red-300 dark:border-red-500/40' },
    updated: { label: 'Updated', icon: 'pi pi-pencil', cls: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300', ring: 'border-orange-300 dark:border-orange-500/40' },
    acknowledged: { label: 'Acknowledged', icon: 'pi pi-eye', cls: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300', ring: 'border-sky-300 dark:border-sky-500/40' },
    resolved: { label: 'Resolved', icon: 'pi pi-check-circle', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', ring: 'border-emerald-300 dark:border-emerald-500/40' }
};
</script>

<template>
    <ol class="border-l border-surface-200 dark:border-surface-700 space-y-6">
        <li v-for="ev in events" :key="ev.id" class="relative ml-6">
            <span class="absolute left-[-19px] top-0 mt-1 h-3.5 w-3.5 rounded-full border-2 border-surface-0 dark:border-surface-900" :class="meta[ev.event]?.cls"></span>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full inline-flex items-center gap-1" :class="meta[ev.event]?.cls">
                    <i :class="meta[ev.event]?.icon"></i>
                    {{ meta[ev.event]?.label || ev.event }}
                </span>
                <span class="text-xs text-surface-500 dark:text-surface-400">
                    {{ new Date(ev.created_at).toLocaleString() }}
                </span>
            </div>
            <p v-if="ev.summary" class="text-sm text-surface-600 dark:text-surface-300 mt-1">{{ ev.summary }}</p>
            <p class="text-xs text-surface-400 dark:text-surface-500 mt-0.5">
                {{ ev.actor_name || 'System' }}
            </p>
        </li>
    </ol>
</template>
