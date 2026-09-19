<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const loading = computed(() => billingStore.loading);
const usage = computed(() => billingStore.usageSummary || billingStore.usage || {});
const subscription = computed(() => billingStore.subscription);

const items = computed(() => {
    const u = usage.value;
    return [
        { key: 'ai', label: 'AI Requests', icon: 'pi pi-sparkles', used: u.ai?.used || 0, limit: u.ai?.limit, percentage: u.ai?.percentage || 0 },
        { key: 'agents', label: 'Agents', icon: 'pi pi-users', used: u.agents?.used || 0, limit: u.agents?.limit, percentage: u.agents?.percentage || 0 },
        { key: 'customers', label: 'Customers', icon: 'pi pi-user-plus', used: u.customers?.used || 0, limit: u.customers?.limit, percentage: u.customers?.percentage || 0 },
        { key: 'widgets', label: 'Widgets', icon: 'pi pi-comments', used: u.widgets?.used || 0, limit: u.widgets?.limit, percentage: u.widgets?.percentage || 0 },
        { key: 'kb_articles', label: 'Knowledge Base', icon: 'pi pi-book', used: u.kb_articles?.used || 0, limit: u.kb_articles?.limit, percentage: u.kb_articles?.percentage || 0 },
        { key: 'conversations', label: 'Conversations', icon: 'pi pi-send', used: u.conversations?.used || 0, limit: u.conversations?.limit, percentage: u.conversations?.percentage || 0 },
        { key: 'storage', label: 'Storage', icon: 'pi pi-database', used: u.storage?.used || 0, limit: u.storage?.limit, percentage: u.storage?.percentage || 0, isStorage: true },
    ];
});

const getProgressClass = (percentage) => {
    if (percentage >= 90) return 'progress-danger';
    if (percentage >= 70) return 'progress-warning';
    return 'progress-success';
};

const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatValue = (value, isStorage) => {
    if (value === 0 || value === null || value === undefined) return 'Unlimited';
    return isStorage ? formatBytes(value) : new Intl.NumberFormat().format(value);
};

onMounted(() => {
    billingStore.fetchUsage();
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Usage</h1>
                <p class="text-surface-600">Track your plan usage and limits</p>
            </div>
            <Button label="Back to Billing" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/billing')" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else>
            <Card v-if="subscription" class="mb-6">
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-surface-500">Current Plan</div>
                            <div class="text-xl font-bold">{{ subscription.plan?.name || 'No Plan' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-surface-500">Billing Cycle</div>
                            <div class="text-xl font-bold capitalize">{{ subscription.billing_cycle }}</div>
                        </div>
                        <Button label="Upgrade Plan" icon="pi pi-arrow-up" @click="router.push('/billing/plans')" />
                    </div>
                </template>
            </Card>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card v-for="item in items" :key="item.key">
                    <template #content>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i :class="item.icon" class="text-primary"></i>
                                <span class="font-medium">{{ item.label }}</span>
                            </div>
                            <span class="text-sm text-surface-500">
                                {{ formatValue(item.used, item.isStorage) }} /
                                {{ formatValue(item.limit, item.isStorage) }}
                            </span>
                        </div>
                        <ProgressBar :value="item.percentage" :class="getProgressClass(item.percentage)"
                            :showValue="false" style="height: 8px" />
                        <div class="text-xs text-surface-500 mt-1 text-right">
                            {{ item.percentage.toFixed(1) }}% used
                        </div>
                    </template>
                </Card>
            </div>
        </template>
    </div>
</template>

<style scoped>
.progress-success :deep(.p-progressbar-value) {
    background: #10b981;
}

.progress-warning :deep(.p-progressbar-value) {
    background: #f59e0b;
}

.progress-danger :deep(.p-progressbar-value) {
    background: #ef4444;
}
</style>
