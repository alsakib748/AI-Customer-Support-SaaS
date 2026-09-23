<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';
import InvoiceDetails from '@/components/billing/InvoiceDetails.vue';
import billingService from '@/services/billingService';

const route = useRoute();
const router = useRouter();
const billingStore = useBillingStore();

const invoice = ref(null);
const loading = ref(false);

const loadInvoice = async () => {
    loading.value = true;
    try {
        const response = await billingService.getInvoice(route.params.id);
        if (response.data.success) {
            invoice.value = response.data.data;
        }
    } catch (e) {
        console.error('Failed to load invoice:', e);
    } finally {
        loading.value = false;
    }
};

const downloadInvoice = () => {
    if (!invoice.value?.invoice_pdf_url) return;
    window.open(invoice.value.invoice_pdf_url, '_blank');
};

onMounted(loadInvoice);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Invoice Details</h1>
                <p class="text-surface-600">View invoice information</p>
            </div>
            <div class="flex gap-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/billing/invoices')" />
                <Button v-if="invoice?.invoice_pdf_url" label="Download PDF" icon="pi pi-download" @click="downloadInvoice" />
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <Card v-else-if="invoice">
            <template #content>
                <InvoiceDetails :invoice="invoice" />
            </template>
        </Card>

        <div v-else class="text-center py-12 text-surface-500">Invoice not found</div>
    </div>
</template>
