<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const filters = reactive({ status: null, provider: null, per_page: 20 });

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Completed', value: 'completed' },
    { label: 'Pending', value: 'pending' },
    { label: 'Failed', value: 'failed' },
    { label: 'Refunded', value: 'refunded' },
];

const providerOptions = [
    { label: 'All', value: null },
    { label: 'Stripe', value: 'stripe' },
    { label: 'PayPal', value: 'paypal' },
];

const loading = computed(() => billingStore.loading);
const payments = computed(() => billingStore.adminPayments);
const total = computed(() => billingStore.adminPagination.payments?.total || 0);

const load = () => billingStore.fetchAdminPayments({ ...filters });

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
                <h1 class="text-2xl font-bold">All Payments</h1>
                <p class="text-surface-600">Platform-wide payment transactions</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/admin/billing')" />
        </div>

        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="load" clearable />
            </div>
            <div class="w-48">
                <Select v-model="filters.provider" :options="providerOptions" optionLabel="label" optionValue="value"
                    placeholder="Provider" class="w-full" @change="load" clearable />
            </div>
        </div>

        <DataTable :value="payments" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="payment_id" header="Payment ID" />
            <Column field="tenant_id" header="Tenant" />
            <Column field="amount" header="Amount">
                <template #body="{ data }">{{ formatCurrency(data.amount, data.currency) }}</template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>
            <Column field="provider" header="Provider" />
            <Column field="paid_at" header="Paid At">
                <template #body="{ data }">{{ formatDate(data.paid_at) }}</template>
            </Column>
        </DataTable>
    </div>
</template>
