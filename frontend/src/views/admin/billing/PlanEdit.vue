<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';
import billingService from '@/services/billingService';

const route = useRoute();
const router = useRouter();
const billingStore = useBillingStore();

const saving = computed(() => billingStore.saving);

const form = reactive({
    name: '',
    slug: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    currency: 'USD',
    trial_days: 14,
    is_active: true,
    is_public: true,
});

const load = async () => {
    const response = await billingService.getPlan(route.params.id);
    if (response.data.success) {
        Object.assign(form, response.data.data);
    }
};

const handleSubmit = async () => {
    try {
        await billingStore.updateAdminPlan(route.params.id, form);
        router.push('/admin/billing/plans');
    } catch (e) {
        console.error(e);
    }
};

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Edit Plan</h1>
                <p class="text-surface-600">Update plan details</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/admin/billing/plans')" />
        </div>

        <Card>
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Name</label>
                        <InputText v-model="form.name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Slug</label>
                        <InputText v-model="form.slug" />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="text-sm font-medium">Description</label>
                        <Textarea v-model="form.description" rows="2" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Monthly Price</label>
                        <InputNumber v-model="form.price_monthly" mode="currency" currency="USD" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Yearly Price</label>
                        <InputNumber v-model="form.price_yearly" mode="currency" currency="USD" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Trial Days</label>
                        <InputNumber v-model="form.trial_days" :min="0" :max="90" />
                    </div>
                    <div class="flex items-center gap-4 pt-6">
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.is_active" binary inputId="is_active_edit" />
                            <label for="is_active_edit">Active</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.is_public" binary inputId="is_public_edit" />
                            <label for="is_public_edit">Public</label>
                        </div>
                    </div>
                </div>

                <Divider />

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" outlined @click="router.push('/admin/billing/plans')" />
                    <Button label="Save Changes" icon="pi pi-check" :loading="saving" @click="handleSubmit" />
                </div>
            </template>
        </Card>
    </div>
</template>
