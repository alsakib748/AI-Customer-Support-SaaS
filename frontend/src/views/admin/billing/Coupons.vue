<script setup>
import { reactive, computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const showDialog = ref(false);
const editing = ref(null);

const filters = reactive({ per_page: 20 });

const loading = computed(() => billingStore.loading);
const saving = computed(() => billingStore.saving);
const coupons = computed(() => billingStore.adminCoupons);
const total = computed(() => billingStore.adminPagination.coupons?.total || 0);

const emptyForm = () => ({
    code: '',
    name: '',
    description: '',
    type: 'percentage',
    value: 10,
    currency: 'USD',
    duration: 'once',
    max_redemptions: 100,
    max_redemptions_per_tenant: 1,
    is_active: true,
});

const form = reactive(emptyForm());

const typeOptions = [
    { label: 'Percentage', value: 'percentage' },
    { label: 'Fixed Amount', value: 'fixed_amount' },
];

const durationOptions = [
    { label: 'Once', value: 'once' },
    { label: 'Repeating', value: 'repeating' },
    { label: 'Forever', value: 'forever' },
];

const load = () => billingStore.fetchAdminCoupons({ ...filters });

const openCreate = () => {
    Object.assign(form, emptyForm());
    editing.value = null;
    showDialog.value = true;
};

const openEdit = (coupon) => {
    Object.assign(form, coupon);
    editing.value = coupon.id;
    showDialog.value = true;
};

const handleSave = async () => {
    try {
        if (editing.value) {
            await billingStore.updateAdminCoupon(editing.value, form);
        } else {
            await billingStore.createAdminCoupon(form);
        }
        showDialog.value = false;
        load();
    } catch (e) {
        console.error(e);
    }
};

const handleDelete = async (coupon) => {
    if (!confirm(`Delete coupon "${coupon.code}"?`)) return;
    try {
        await billingStore.deleteAdminCoupon(coupon.id);
    } catch (e) {
        console.error(e);
    }
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    load();
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Coupons</h1>
                <p class="text-surface-600">Manage discount coupons</p>
            </div>
            <div class="flex gap-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                    @click="router.push('/admin/billing')" />
                <Button label="Create Coupon" icon="pi pi-plus" @click="openCreate" />
            </div>
        </div>

        <DataTable :value="coupons" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="code" header="Code" />
            <Column field="name" header="Name" />
            <Column field="type" header="Type">
                <template #body="{ data }">
                    <Tag :value="data.type_label" />
                </template>
            </Column>
            <Column field="formatted_value" header="Value" />
            <Column field="times_redeemed" header="Redeemed" />
            <Column field="is_active" header="Active">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Yes' : 'No'" :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column field="expires_at" header="Expires">
                <template #body="{ data }">{{ formatDate(data.expires_at) }}</template>
            </Column>
            <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" severity="info" text rounded @click="openEdit(data)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded @click="handleDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="showDialog" :header="editing ? 'Edit Coupon' : 'Create Coupon'"
            :style="{ width: '600px' }" modal>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Code *</label>
                    <InputText v-model="form.code" :disabled="!!editing" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Name *</label>
                    <InputText v-model="form.name" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label class="text-sm font-medium">Description</label>
                    <Textarea v-model="form.description" rows="2" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Type</label>
                    <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Value</label>
                    <InputNumber v-model="form.value" :min="0" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Duration</label>
                    <Select v-model="form.duration" :options="durationOptions" optionLabel="label"
                        optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Max Redemptions</label>
                    <InputNumber v-model="form.max_redemptions" :min="1" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Max per Tenant</label>
                    <InputNumber v-model="form.max_redemptions_per_tenant" :min="1" />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <Checkbox v-model="form.is_active" binary inputId="coupon_active" />
                    <label for="coupon_active">Active</label>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showDialog = false" />
                <Button :label="editing ? 'Save' : 'Create'" :loading="saving" @click="handleSave" />
            </template>
        </Dialog>
    </div>
</template>
