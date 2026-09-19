<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const filters = reactive({ status: null, per_page: 20 });

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Open', value: 'open' },
    { label: 'Paid', value: 'paid' },
    { label: 'Void', value: 'void' },
];

const loading = computed(() => billingStore.loading);
const invoices = computed(() => billingStore.adminInvoices);
const total = computed(() => billingStore.adminPagination.invoices?.total || 0);

const load = () => billingStore.fetchAdminInvoices({ ...filters });

const onPageChange = (event) => {
    filters.per_page = event.rows;
    load();
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatCurrency = (amount, currency = 'USD') =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">All Invoices</h1>
                <p class="text-surface-600">Platform-wide invoices</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/admin/billing')" />
        </div>

        <div class="mb-4 flex gap-3 items-center">
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="load" clearable />
            </div>
        </div>

        <DataTable :value="invoices" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="invoice_number" header="Invoice #" />
            <Column field="tenant_id" header="Tenant" />
            <Column field="total" header="Amount">
                <template #body="{ data }">{{ formatCurrency(data.total, data.currency) }}</template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>
            <Column field="due_at" header="Due">
                <template #body="{ data }">{{ formatDate(data.due_at) }}</template>
            </Column>
            <Column field="created_at" header="Created">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
        </DataTable>
    </div>
</template>
