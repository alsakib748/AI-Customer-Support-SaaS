<!-- src/views/admin/PlatformAnalytics.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import MetricCard from '@/components/analytics/MetricCard.vue';
import { toast } from 'vue3-toastify';

const overview = ref(null);
const tenantUsage = ref([]);
const loading = ref(false);
const loadingUsage = ref(false);

const loadData = async () => {
    loading.value = true;
    try {
        const [overviewRes, usageRes] = await Promise.all([
            api.get('/admin/analytics/overview'),
            api.get('/admin/analytics/tenant-usage'),
        ]);

        overview.value = overviewRes.data.data;
        tenantUsage.value = usageRes.data.data;
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to load platform analytics.');
    } finally {
        loading.value = false;
    }
};

onMounted(loadData);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Platform Analytics</h1>
                <p class="text-surface-600 dark:text-surface-400">
                    Platform-wide metrics across all tenants
                </p>
            </div>
            <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined :loading="loading"
                @click="loadData" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else-if="overview">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                <MetricCard title="Total Tenants" :value="overview.tenants.total" />
                <MetricCard title="Active Tenants" :value="overview.tenants.active" />
                <MetricCard title="Trial Tenants" :value="overview.tenants.trial" />
                <MetricCard title="Total Users" :value="overview.users.total" />
                <MetricCard title="Active Users" :value="overview.users.active" />
            </div>

            <Card>
                <template #title>Per-Tenant Usage</template>
                <template #content>
                    <DataTable :value="tenantUsage" :loading="loadingUsage" paginator :rows="10"
                        responsiveLayout="scroll">
                        <Column field="tenant_id" header="Tenant ID">
                            <template #body="{ data }">
                                <span class="font-mono text-xs">{{ data.tenant_id }}</span>
                            </template>
                        </Column>
                        <Column field="tenant_name" header="Name" />
                        <Column field="conversations" header="Conversations">
                            <template #body="{ data }">
                                {{ data.conversations ?? '—' }}
                            </template>
                        </Column>
                        <Column field="customers" header="Customers">
                            <template #body="{ data }">
                                {{ data.customers ?? '—' }}
                            </template>
                        </Column>
                        <Column field="tickets" header="Tickets">
                            <template #body="{ data }">
                                {{ data.tickets ?? '—' }}
                            </template>
                        </Column>
                        <Column field="ai_requests" header="AI Requests">
                            <template #body="{ data }">
                                {{ data.ai_requests ?? '—' }}
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </template>
    </div>
</template>