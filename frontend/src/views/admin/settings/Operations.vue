<script setup>
import { ref, onMounted } from 'vue';
import SettingsGroupForm from '@/components/admin/settings/SettingsGroupForm.vue';
import { useSystemSettingsStore } from '@/stores/systemSettings';

const store = useSystemSettingsStore();
const loadingHealth = ref(true);

const statusClass = (status) => {
    switch (status) {
        case 'ok':
            return 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300';
        case 'warning':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
        case 'error':
            return 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300';
        default:
            return 'bg-surface-200 text-surface-700 dark:bg-surface-700 dark:text-surface-200';
    }
};

onMounted(async () => {
    try {
        await store.fetchHealth();
    } finally {
        loadingHealth.value = false;
    }
});
</script>

<template>
    <SettingsGroupForm group="operations" />

    <div class="mt-8 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="pi pi-heart text-primary"></i>
                <span class="font-semibold text-surface-900 dark:text-surface-0">System health</span>
            </div>
            <button type="button" class="text-sm text-primary hover:underline" :disabled="loadingHealth" @click="store.fetchHealth()">
                <i class="pi" :class="loadingHealth ? 'pi-spin pi-spinner' : 'pi-refresh'"></i>
                Refresh
            </button>
        </div>

        <div v-if="loadingHealth && !store.health" class="flex justify-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
        </div>

        <div v-else-if="store.health" class="space-y-3">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full uppercase" :class="statusClass(store.health.overall)">
                    Overall: {{ store.health.overall }}
                </span>
                <span class="text-xs text-surface-500 dark:text-surface-400">
                    {{ store.health.counts.ok }} OK · {{ store.health.counts.warning }} warning · {{ store.health.counts.error }} error
                </span>
            </div>

            <div v-for="(check, key) in store.health.checks" :key="key" class="flex items-start gap-3 py-2 border-t border-surface-100 dark:border-surface-800">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full uppercase mt-0.5" :class="statusClass(check.status)">
                    {{ check.status }}
                </span>
                <div>
                    <div class="text-sm font-medium text-surface-900 dark:text-surface-0">{{ check.label }}</div>
                    <div class="text-sm text-surface-500 dark:text-surface-400">{{ check.summary }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
