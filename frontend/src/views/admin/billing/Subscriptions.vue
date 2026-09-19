<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const filters = reactive({ status: null, per_page: 20 });

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Trialing', value: 'trialing' },
    { label: 'Active', value: 'active' },
    { label: 'Past Due', value: 'past_due' },
    { label: 'Cancelled', value: 'cancelled' },
    { label: 'Expired', value: 'expired' },
];

const loading = computed(() => billingStore.loading);
const subscriptions = computed(() => billingStore.adminSubscriptions);
const total = computed(() => billingStore.adminPagination.subscriptions?.total || 0);

const load = () => billingStore.fetchAdminSubscriptions({ ...filters });

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
                <h1 class="text-2xl font-bold">Subscriptions</h1>
                <p class="text-surface-600">All tenant subscriptions</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined
                @click="router.push('/admin/billing')" />
        </div>

        <div class="mb-4 flex gap-3 items-center">
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="load" clearable />
            </div>
        </div>

        <DataTable :value="subscriptions" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="tenant_id" header="Tenant" />
            <Column field="plan.name" header="Plan" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </Template>
            </Column>
            <Column field="billing_cycle" header="Cycle" />
            <Column field="next_billing_at" header="Next Billing">
                <template #body="{ data }">{{ formatDate(data.next_billing_at) }}</template>
            </Column>
            <Column field="created_at" header="Created">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>
        </DataTable>
    </div>
</template>
