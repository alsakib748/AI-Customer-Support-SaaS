<!-- src/components/billing/InvoiceDetails.vue -->
<script setup>
const props = defineProps({
    invoice: { type: Object, required: true },
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
};
</script>

<template>
    <div v-if="invoice" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold">{{ invoice.invoice_number }}</h3>
                <Tag :value="invoice.status_label" :severity="invoice.status_color" class="mt-1" />
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold">{{ invoice.formatted_total }}</div>
            </div>
        </div>

        <Divider />

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-surface-500">Billing Period</label>
                <div class="font-medium">
                    {{ formatDate(invoice.period_starts_at) }} - {{ formatDate(invoice.period_ends_at) }}
                </div>
            </div>
            <div>
                <label class="text-sm text-surface-500">Due Date</label>
                <div class="font-medium">{{ formatDate(invoice.due_at) }}</div>
            </div>
            <div>
                <label class="text-sm text-surface-500">Subtotal</label>
                <div class="font-medium">{{ formatCurrency(invoice.subtotal, invoice.currency) }}</div>
            </div>
            <div v-if="invoice.discount_amount > 0">
                <label class="text-sm text-surface-500">Discount</label>
                <div class="font-medium text-success">
                    -{{ formatCurrency(invoice.discount_amount, invoice.currency) }}
                </div>
            </div>
        </div>

        <Divider />

        <div>
            <h4 class="font-semibold mb-2">Line Items</h4>
            <div v-for="(item, index) in invoice.line_items" :key="index"
                class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                <div>
                    <div class="font-medium">{{ item.description }}</div>
                    <div class="text-sm text-surface-500">Qty: {{ item.quantity }}</div>
                </div>
                <div class="font-medium">{{ formatCurrency(item.total, invoice.currency) }}</div>
            </div>
        </div>
    </div>
</template>