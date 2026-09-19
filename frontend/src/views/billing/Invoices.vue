<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const showDetailsDialog = ref(false);
const selectedInvoice = ref(null);

const filters = reactive({
    status: null,
    date_from: null,
    date_to: null,
    per_page: 20,
});

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Paid', value: 'paid' },
    { label: 'Open', value: 'open' },
    { label: 'Draft', value: 'draft' },
    { label: 'Void', value: 'void' },
];

const loading = computed(() => billingStore.loading);
const invoices = computed(() => billingStore.invoices);
const totalInvoices = computed(() => billingStore.invoicesPagination?.total || 0);

const loadData = () => billingStore.fetchInvoices({ ...filters });

const applyFilters = () => loadData();

const clearFilters = () => {
    Object.assign(filters, { status: null, date_from: null, date_to: null, per_page: 20 });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    loadData();
};

const viewInvoice = (invoice) => {
    router.push(`/billing/invoices/${invoice.id}`);
};

const downloadInvoice = (invoice) => {
    if (!invoice.invoice_pdf_url) return;
    window.open(invoice.invoice_pdf_url, '_blank');
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
watch(() => filters.status, applyFilters);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Invoices</h1>
                <p class="text-surface-600">View and download your invoices</p>
            </div>
            <Button label="Back to Billing" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/billing')" />
        </div>

        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-48">
                <Calendar v-model="filters.date_from" placeholder="From" class="w-full" @date-select="applyFilters" />
            </div>
            <div class="w-48">
                <Calendar v-model="filters.date_to" placeholder="To" class="w-full" @date-select="applyFilters" />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <DataTable :value="invoices" :loading="loading" paginator :rows="filters.per_page" :totalRecords="totalInvoices"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="invoice_number" header="Invoice #" sortable>
                <template #body="{ data }">
                    <span class="font-mono font-medium">{{ data.invoice_number }}</span>
                </template>
            </Column>
            <Column field="total" header="Amount" sortable>
                <template #body="{ data }">
                    <span class="font-medium">{{ formatCurrency(data.total, data.currency) }}</span>
                </template>
            </Column>
            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>
            <Column field="due_at" header="Due Date" sortable>
                <template #body="{ data }">{{ formatDate(data.due_at) }}</template>
            </Column>
            <Column field="created_at" header="Created" sortable>
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 150px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewInvoice(data)" />
                        <Button icon="pi pi-download" severity="secondary" text rounded
                            :disabled="!data.invoice_pdf_url" @click="downloadInvoice(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
