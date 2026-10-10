<script setup>
import { ref, onMounted } from 'vue';
import { toast } from 'vue3-toastify';
import SettingsGroupForm from '@/components/admin/settings/SettingsGroupForm.vue';
import IntegrationStatusCard from '@/components/admin/settings/IntegrationStatusCard.vue';
import { useSystemSettingsStore } from '@/stores/systemSettings';

const store = useSystemSettingsStore();
const loading = ref(true);
const testingProvider = ref(null);

onMounted(async () => {
    try {
        await store.fetchIntegrations();
    } finally {
        loading.value = false;
    }
});

const test = async (provider) => {
    testingProvider.value = provider;
    try {
        const res = await store.testIntegration(provider);
        if (res.data?.success) toast.success(res.message || 'Connection OK.');
        else toast.error(res.data?.message || res.message || 'Connection failed.');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Connection test failed.');
    } finally {
        testingProvider.value = null;
    }
};
</script>

<template>
    <div>
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">Connection status</h2>
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                Live checks contact the provider directly. Configuration checks only confirm credentials are present.
            </p>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
            <IntegrationStatusCard
                v-for="item in store.integrations"
                :key="item.provider"
                :item="item"
                :testing="testingProvider === item.provider"
                @test="test"
            />
        </div>

        <SettingsGroupForm group="integrations" />
    </div>
</template>
