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
    { label: 'Manual', value: 'manual' },
];

const loading = computed(() => billingStore.loading);
const payments = computed(() => billingStore.payments);
const paymentStats = computed(() => billingStore.paymentStats);
const totalPayments = computed(() => billingStore.paymentsPagination?.total || 0);

const loadData = async () => {
    await Promise.allSettled([
        billingStore.fetchPayments({ ...filters }),
        billingStore.fetchPaymentStatistics(),
    ]);
};

const applyFilters = () => loadData();

const clearFilters = () => {
    Object.assign(filters, { status: null, provider: null, per_page: 20 });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    loadData();
};

const viewInvoice = (invoiceId) => {
    router.push(`/billing/invoices/${invoiceId}`);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const formatCurrency = (amount, currency = 'USD') =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);

onMounted(loadData);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Payment History</h1>
                <p class="text-surface-600 dark:text-surface-400">View all your payment transactions</p>
            </div>
            <Button label="Back to Billing" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/billing')" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card><template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ paymentStats.total || 0 }}</div>
                        <div class="text-sm text-surface-600">Total Payments</div>
                    </div>
                </template></Card>
            <Card><template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">{{ formatCurrency(paymentStats.total_revenue || 0)
                            }}</div>
                        <div class="text-sm text-surface-600">Total Revenue</div>
                    </div>
                </template></Card>
            <Card><template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-danger">{{ paymentStats.failed || 0 }}</div>
                        <div class="text-sm text-surface-600">Failed</div>
                    </div>
                </template></Card>
            <Card><template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning">{{ formatCurrency(paymentStats.total_refunded || 0)
                            }}</div>
                        <div class="text-sm text-surface-600">Refunded</div>
                    </div>
                </template></Card>
        </div>

        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-48">
                <Select v-model="filters.provider" :options="providerOptions" optionLabel="label" optionValue="value"
                    placeholder="Provider" class="w-full" @change="applyFilters" clearable />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <DataTable :value="payments" :loading="loading" paginator :rows="filters.per_page" :totalRecords="totalPayments"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="payment_id" header="Payment ID">
                <template #body="{ data }">
                    <span class="font-mono text-sm">{{ data.payment_id }}</span>
                </template>
            </Column>
            <Column field="amount" header="Amount">
                <template #body="{ data }">
                    <span class="font-medium">{{ formatCurrency(data.amount, data.currency) }}</span>
                </template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>
            <Column field="provider" header="Provider">
                <template #body="{ data }">
                    <span class="capitalize">{{ data.provider }}</span>
                </template>
            </Column>
            <Column field="payment_method" header="Method">
                <template #body="{ data }">
                    <span class="capitalize">{{ data.payment_method }}</span>
                    <span v-if="data.last_four" class="text-surface-500"> •••• {{ data.last_four }}</span>
                </template>
            </Column>
            <Column field="paid_at" header="Paid At">
                <template #body="{ data }">{{ formatDate(data.paid_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 100px">
                <template #body="{ data }">
                    <Button v-if="data.invoice_id" icon="pi pi-eye" severity="info" text rounded
                        @click="viewInvoice(data.invoice_id)" />
                </template>
            </Column>
        </DataTable>

        <Toast />
    </div>
</template>
