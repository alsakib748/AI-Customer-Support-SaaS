<script setup>
import { ref, onMounted } from 'vue';
import { useSystemSettingsStore } from '@/stores/systemSettings';

const store = useSystemSettingsStore();
const loading = ref(true);

onMounted(async () => {
    try {
        await store.fetchIndex();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-16">
            <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <RouterLink
                v-for="group in store.groups"
                :key="group.key"
                :to="`/admin/settings/${group.key}`"
                class="rounded-xl border border-surface-200 dark:border-surface-700 p-5 hover:border-primary hover:shadow-sm transition-all group"
            >
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-950 text-primary flex items-center justify-center">
                        <i :class="group.icon"></i>
                    </span>
                    <span class="font-semibold text-surface-900 dark:text-surface-0">{{ group.label }}</span>
                </div>
                <p class="text-sm text-surface-500 dark:text-surface-400">{{ group.description }}</p>
                <span class="inline-flex items-center gap-1 text-sm text-primary mt-4">
                    Configure <i class="pi pi-arrow-right text-xs"></i>
                </span>
            </RouterLink>
        </div>
    </div>
</template>
