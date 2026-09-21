<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import billingService from '@/services/billingService';
import { toast } from 'vue3-toastify';

const route = useRoute();
const planId = route.params.id;

const prices = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);

const form = reactive({
    provider: 'stripe',
    billing_cycle: 'monthly',
    currency: 'USD',
    provider_price_id: '',
    amount: 0,
    is_active: true,
});

const providerOptions = [
    { label: 'Stripe', value: 'stripe' },
    { label: 'PayPal', value: 'paypal' },
];

const cycleOptions = [
    { label: 'Monthly', value: 'monthly' },
    { label: 'Yearly', value: 'yearly' },
];

const load = async () => {
    loading.value = true;
    try {
        const r = await billingService.getPlanProviderPrices(planId);
        if (r.data.success) prices.value = r.data.data;
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    Object.assign(form, {
        provider: 'stripe',
        billing_cycle: 'monthly',
        currency: 'USD',
        provider_price_id: '',
        amount: 0,
        is_active: true,
    });
    showDialog.value = true;
};

const handleSave = async () => {
    saving.value = true;
    try {
        await billingService.createPlanProviderPrice(planId, form);
        toast.success('Saved');
        showDialog.value = false;
        load();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed');
    } finally {
        saving.value = false;
    }
};

const handleDelete = async (row) => {
    if (!confirm('Delete this price?')) return;
    try {
        await billingService.deletePlanProviderPrice(row.id);
        toast.success('Deleted');
        load();
    } catch (e) {
        toast.error('Failed to delete');
    }
};

const formatCurrency = (amount, currency = 'USD') =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Provider Prices</h1>
                <p class="text-surface-600">Map this plan to Stripe / PayPal prices</p>
            </div>
            <div class="flex gap-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                    @click="$router.push('/admin/billing/plans')" />
                <Button label="Add Price" icon="pi pi-plus" @click="openCreate" />
            </div>
        </div>

        <DataTable :value="prices" :loading="loading" class="w-full">
            <Column field="provider" header="Provider" />
            <Column field="billing_cycle" header="Cycle" />
            <Column field="currency" header="Currency" />
            <Column field="provider_price_id" header="Provider Price ID" />
            <Column field="amount" header="Amount">
                <template #body="{ data }">{{ formatCurrency(data.amount, data.currency) }}</template>
            </Column>
            <Column field="is_active" header="Active">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Yes' : 'No'" :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Actions" style="width: 100px">
                <template #body="{ data }">
                    <Button icon="pi pi-trash" severity="danger" text rounded @click="handleDelete(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="showDialog" header="Add Provider Price" :style="{ width: '500px' }" modal>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Provider</label>
                    <Select v-model="form.provider" :options="providerOptions" optionLabel="label"
                        optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Billing Cycle</label>
                    <Select v-model="form.billing_cycle" :options="cycleOptions" optionLabel="label"
                        optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Currency</label>
                    <InputText v-model="form.currency" maxlength="3" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Amount</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="USD" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label class="text-sm font-medium">Provider Price ID</label>
                    <InputText v-model="form.provider_price_id" placeholder="price_xxx or P-xxx" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_active" binary inputId="active" />
                    <label for="active">Active</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showDialog = false" />
                <Button label="Save" :loading="saving" @click="handleSave" />
            </template>
        </Dialog>
    </div>
</template>
