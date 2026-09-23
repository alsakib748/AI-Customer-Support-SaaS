<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';

const data = ref(null);
const loading = ref(false);
const error = ref(null);

const kpis = computed(() => {
    const t = data.value?.tenants || {};
    const u = data.value?.users || {};
    return [
        { title: 'Total Tenants', value: t.total ?? 0, icon: 'pi pi-building', color: 'bg-primary' },
        { title: 'Active Tenants', value: t.active ?? 0, icon: 'pi pi-bolt', color: 'bg-green-500' },
        { title: 'Trial Tenants', value: t.trial ?? 0, icon: 'pi pi-hourglass', color: 'bg-purple-500' },
        { title: 'Total Users', value: u.total ?? 0, icon: 'pi pi-users', color: 'bg-blue-500' },
        { title: 'Active Users', value: u.active ?? 0, icon: 'pi pi-user-check', color: 'bg-orange-500' }
    ];
});

onMounted(async () => {
    loading.value = true;
    try {
        const response = await api.get('/admin/analytics/overview');
        if (response.data.success) data.value = response.data.data;
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load platform metrics.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <Card v-for="kpi in kpis" :key="kpi.title" class="relative">
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-surface-500 mb-1">{{ kpi.title }}</div>
                        <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                            {{ loading ? '-' : kpi.value.toLocaleString() }}
                        </div>
                    </div>
                    <i :class="kpi.icon" class="text-2xl text-primary"></i>
                </div>
            </template>
        </Card>
    </div>

    <Message v-if="error" severity="warn" :closable="false">
        {{ error }}
    </Message>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 xl:col-span-7">
            <Card>
                <template #title>Platform</template>
                <template #content>
                    <p class="text-surface-600 dark:text-surface-400">
                        Manage tenants, users, platform analytics and billing from the sidebar. Tenant-level features (customers, conversations, tickets, knowledge base, AI) are kept separate — enter a tenant from the Tenants page to inspect its
                        workspace.
                    </p>
                </template>
            </Card>
        </div>
        <div class="col-span-12 xl:col-span-5">
            <Card>
                <template #title>System</template>
                <template #content>
                    <ul class="space-y-2 text-surface-600 dark:text-surface-400">
                        <li class="flex justify-between"><span>Database</span><i class="pi pi-check-circle text-green-500"></i></li>
                        <li class="flex justify-between"><span>Queue</span><i class="pi pi-check-circle text-green-500"></i></li>
                        <li class="flex justify-between"><span>Storage</span><i class="pi pi-check-circle text-green-500"></i></li>
                        <li class="flex justify-between"><span>AI Provider</span><i class="pi pi-check-circle text-green-500"></i></li>
                    </ul>
                </template>
            </Card>
        </div>
    </div>
</template>
