<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import auditService from '@/services/auditService';
import { toast } from 'vue3-toastify';

const loading = ref(false);
const logs = ref([]);
const meta = reactive({ current_page: 1, per_page: 25, total: 0, last_page: 1 });
const stats = ref({ total: 0, by_action: {}, by_resource_type: {} });

const filters = reactive({
    search: '',
    action: null,
    resource_type: null,
    tenant_id: '',
    date_from: null,
    date_to: null,
    per_page: 25
});

const actionOptions = computed(() => [
    { label: 'All Actions', value: null },
    ...Object.keys(stats.value.by_action || {})
        .sort()
        .map((action) => ({ label: action, value: action }))
]);

const resourceOptions = computed(() => [
    { label: 'All Resources', value: null },
    ...Object.keys(stats.value.by_resource_type || {})
        .sort()
        .map((type) => ({ label: type, value: type }))
]);

const toastError = (error, fallback) => {
    if (!error?.config?.skipErrorToast) {
        toast.error(error?.response?.data?.message || fallback);
    }
};

const loadStats = async () => {
    try {
        const { data } = await auditService.getStatistics();
        stats.value = data.data || stats.value;
    } catch (error) {
        toastError(error, 'Failed to load audit statistics.');
    }
};

const load = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            per_page: filters.per_page,
            search: filters.search || undefined,
            action: filters.action || undefined,
            resource_type: filters.resource_type || undefined,
            tenant_id: filters.tenant_id || undefined,
            date_from: filters.date_from || undefined,
            date_to: filters.date_to ? `${filters.date_to}T23:59:59` : undefined
        };

        const { data } = await auditService.getLogs(params);
        logs.value = data.data || [];
        Object.assign(meta, data.meta || {});
    } catch (error) {
        toastError(error, 'Failed to load audit logs.');
        logs.value = [];
    } finally {
        loading.value = false;
    }
};

const debounceTimer = ref(null);
const onSearchInput = () => {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(() => load(1), 400);
};

const applyFilters = () => load(1);

const resetFilters = () => {
    filters.search = '';
    filters.action = null;
    filters.resource_type = null;
    filters.tenant_id = '';
    filters.date_from = null;
    filters.date_to = null;
    load(1);
};

const onPage = (event) => {
    filters.per_page = event.rows;
    load(event.page + 1);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(() => {
    load(1);
    loadStats();
});
</script>

<template>
    <div class="p-4 md:p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Audit Logs</h1>
            <p class="text-surface-600 dark:text-surface-400">Platform-wide activity trail across every workspace.</p>
        </div>

        <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Total Events</p>
                <p class="mt-1 text-2xl font-bold">{{ stats.total ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Distinct Actions</p>
                <p class="mt-1 text-2xl font-bold text-info">{{ Object.keys(stats.by_action || {}).length }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Resource Types</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ Object.keys(stats.by_resource_type || {}).length }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Recent (10)</p>
                <p class="mt-1 text-2xl font-bold text-success">{{ (stats.recent || []).length }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <InputText v-model="filters.search" placeholder="Search action or resource" class="w-64" @input="onSearchInput" />
            <Select v-model="filters.action" :options="actionOptions" optionLabel="label" optionValue="value" class="w-52" @change="applyFilters" />
            <Select v-model="filters.resource_type" :options="resourceOptions" optionLabel="label" optionValue="value" class="w-48" @change="applyFilters" />
            <InputText v-model="filters.tenant_id" placeholder="Tenant ID" class="w-48" @change="applyFilters" />
            <InputText v-model="filters.date_from" type="date" class="w-44" @change="applyFilters" />
            <InputText v-model="filters.date_to" type="date" class="w-44" @change="applyFilters" />
            <Button label="Reset" severity="secondary" text @click="resetFilters" />
        </div>

        <DataTable :value="logs" :loading="loading" paginator :rows="filters.per_page" :totalRecords="meta.total" :lazy="true" class="w-full" @page="onPage">
            <template #empty>
                <div class="py-12 text-center text-surface-400">No audit events match your filters.</div>
            </template>

            <Column header="When" style="min-width: 170px">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>

            <Column field="user_name" header="Actor" style="min-width: 160px">
                <template #body="{ data }">{{ data.user_name }}</template>
            </Column>

            <Column header="Action" style="min-width: 190px">
                <template #body="{ data }">
                    <Tag :value="data.action_label || data.action" severity="secondary" />
                </template>
            </Column>

            <Column header="Resource" style="min-width: 180px">
                <template #body="{ data }">{{ data.resource_name || '—' }}</template>
            </Column>

            <Column field="tenant_id" header="Tenant" style="min-width: 130px">
                <template #body="{ data }">
                    <span class="font-mono text-xs">{{ data.tenant_id || 'platform' }}</span>
                </template>
            </Column>

            <Column field="ip_address" header="IP" style="min-width: 130px">
                <template #body="{ data }">
                    <span class="font-mono text-xs">{{ data.ip_address || '—' }}</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
