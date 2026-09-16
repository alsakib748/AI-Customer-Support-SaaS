<!-- src/views/billing/Invoices.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useBillingStore } from '@/stores/billing';
import { useToast } from 'primevue/usetoast';

const billingStore = useBillingStore();
const toast = useToast();

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
const totalInvoices = computed(() => billingStore.pagination?.total || 0);

const loadData = async () => {
    await billingStore.fetchInvoices({ ...filters });
};

const applyFilters = () => {
    billingStore.filters = { ...filters };
    loadData();
};

const clearFilters = () => {
    Object.assign(filters, {
        status: null,
        date_from: null,
        date_to: null,
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    loadData();
};

const viewInvoice = (invoice) => {
    selectedInvoice.value = invoice;
    showDetailsDialog.value = true;
};

const downloadInvoice = async (invoice) => {
    if (!invoice.invoice_pdf_url) {
        toast.warning('PDF not available');
        return;
    }
    window.open(invoice.invoice_pdf_url, '_blank');
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(amount);
};

onMounted(() => {
    loadData();
});

watch(() => filters.status, () => applyFilters());
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Invoices</h1>
                <p class="text-surface-600">View and download your invoices</p>
            </div>
            <Button label="Back to Billing" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="$router.push('/billing')" />
        </div>

        <!-- Filters -->
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

        <!-- Invoices Table -->
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
                <template #body="{ data }">
                    {{ formatDate(data.due_at) }}
                </template>
            </Column>
            <Column field="paid_at" header="Paid At" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.paid_at) }}
                </template>
            </Column>
            <Column field="created_at" header="Created" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.created_at) }}
                </template>
            </Column>
            <Column header="Actions" style="width: 150px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewInvoice(data)"
                            tooltip="View" />
                        <Button icon="pi pi-download" severity="secondary" text rounded @click="downloadInvoice(data)"
                            tooltip="Download" :disabled="!data.invoice_pdf_url" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Invoice Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" header="Invoice Details" :style="{ width: '600px' }" modal>
            <div v-if="selectedInvoice" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold">{{ selectedInvoice.invoice_number }}</h3>
                        <Tag :value="selectedInvoice.status_label" :severity="selectedInvoice.status_color"
                            class="mt-1" />
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold">{{ formatCurrency(selectedInvoice.total,
                            selectedInvoice.currency) }}
                        </div>
                    </div>
                </div>

                <Divider />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-surface-500">Billing Period</label>
                        <div class="font-medium">
                            {{ formatDate(selectedInvoice.period_starts_at) }} -
                            {{ formatDate(selectedInvoice.period_ends_at) }}
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Due Date</label>
                        <div class="font-medium">{{ formatDate(selectedInvoice.due_at) }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Subtotal</label>
                        <div class="font-medium">{{ formatCurrency(selectedInvoice.subtotal, selectedInvoice.currency)
                        }}</div>
                    </div>
                    <div v-if="selectedInvoice.discount_amount > 0">
                        <label class="text-sm text-surface-500">Discount</label>
                        <div class="font-medium text-success">
                            -{{ formatCurrency(selectedInvoice.discount_amount, selectedInvoice.currency) }}
                        </div>
                    </div>
                </div>

                <Divider />

                <div>
                    <h4 class="font-semibold mb-2">Line Items</h4>
                    <div v-for="(item, index) in selectedInvoice.line_items" :key="index"
                        class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                        <div>
                            <div class="font-medium">{{ item.description }}</div>
                            <div class="text-sm text-surface-500">Qty: {{ item.quantity }}</div>
                        </div>
                        <div class="font-medium">{{ formatCurrency(item.total, selectedInvoice.currency) }}</div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showDetailsDialog = false" />
                <Button v-if="selectedInvoice?.invoice_pdf_url" label="Download PDF" icon="pi pi-download"
                    severity="primary" @click="downloadInvoice(selectedInvoice)" />
            </template>
        </Dialog>

        <Toast />
    </div>
</template>
