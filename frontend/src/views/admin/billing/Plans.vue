<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const loading = computed(() => billingStore.loading);
const plans = computed(() => billingStore.adminPlans);

onMounted(() => billingStore.fetchAdminPlans());

const handleDelete = async (plan) => {
    if (!confirm(`Delete plan "${plan.name}"?`)) return;
    try {
        await billingStore.deleteAdminPlan(plan.id);
    } catch (e) {
        console.error(e);
    }
};

const formatCurrency = (amount, currency = 'USD') =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Manage Plans</h1>
                <p class="text-surface-600">Create and manage subscription plans</p>
            </div>
            <div class="flex gap-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                    @click="router.push('/admin/billing')" />
                <Button label="Create Plan" icon="pi pi-plus" @click="router.push('/admin/billing/plans/create')" />
                <Button icon="pi pi-dollar" severity="success" text rounded
                    @click="$router.push(`/admin/billing/plans/${data.id}/prices`)" />
            </div>
        </div>

        <DataTable :value="plans" :loading="loading" paginator :rows="20" class="w-full">
            <Column field="name" header="Name" sortable />
            <Column field="slug" header="Slug" sortable />
            <Column field="price_monthly" header="Monthly">
                <template #body="{ data }">{{ formatCurrency(data.price_monthly, data.currency) }}</template>
            </Column>
            <Column field="price_yearly" header="Yearly">
                <template #body="{ data }">{{ formatCurrency(data.price_yearly, data.currency) }}</template>
            </Column>
            <Column field="trial_days" header="Trial Days" />
            <Column field="is_active" header="Active">
                <template #body="{ data }">
                    <Tag :value="data.is_active ? 'Yes' : 'No'" :severity="data.is_active ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column header="Actions" style="width: 150px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" severity="info" text rounded
                            @click="router.push(`/admin/billing/plans/${data.id}/edit`)" />
                        <Button icon="pi pi-trash" severity="danger" text rounded @click="handleDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
