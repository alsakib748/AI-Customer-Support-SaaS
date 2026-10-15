<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const filters = reactive({ page: 1, per_page: 20 });

const loading = computed(() => billingStore.loading);
const saving = computed(() => billingStore.saving);
const overdue = computed(() => billingStore.adminOverdue);
const total = computed(() => billingStore.adminPagination.overdue?.total || 0);

const load = () => billingStore.fetchAdminOverdue({ ...filters });

const onPageChange = (event) => {
    filters.page = event.page + 1;
    filters.per_page = event.rows;
    load();
};

const sendEmail = async (row, finalNotice = false) => {
    try {
        await billingStore.sendOverdueEmail(row.id, { final_notice: finalNotice });
    } catch {
        // errors surfaced via toast in the store
    }
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
                <h1 class="text-2xl font-bold">Overdue &amp; Suspended Payments</h1>
                <p class="text-surface-600">Tenants with past-due or suspended subscriptions</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/admin/billing')" />
        </div>

        <DataTable :value="overdue" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total" :lazy="true" @page="onPageChange" class="w-full">
            <Column header="Tenant">
                <template #body="{ data }">
                    <div class="font-medium">{{ data.tenant_name || data.tenant_id }}</div>
                    <div class="text-xs text-surface-500">{{ data.tenant_id }}</div>
                </template>
            </Column>
            <Column field="plan_name" header="Plan" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="data.status === 'suspended' ? 'danger' : 'warning'" />
                </template>
            </Column>
            <Column header="Amount Due">
                <template #body="{ data }">
                    <span v-if="data.amount_due" class="font-medium">{{ data.amount_due }}</span>
                    <span v-else class="text-surface-400">—</span>
                </template>
            </Column>
            <Column header="Invoice">
                <template #body="{ data }">{{ data.invoice_number || '—' }}</template>
            </Column>
            <Column header="Due Date">
                <template #body="{ data }">{{ formatDate(data.due_at) }}</template>
            </Column>
            <Column header="Days Past Due">
                <template #body="{ data }">
                    <span v-if="data.days_past_due !== null" class="font-bold text-warning">{{ data.days_past_due }}</span>
                    <span v-else class="text-surface-400">—</span>
                </template>
            </Column>
            <Column header="Actions">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button label="Send Email" icon="pi pi-envelope" size="small" :disabled="saving" @click="sendEmail(data, false)" />
                        <Button label="Final Notice" icon="pi pi-exclamation-triangle" size="small" severity="danger" outlined :disabled="saving" @click="sendEmail(data, true)" />
                    </div>
                </template>
            </Column>
            <template #empty>No overdue subscriptions.</template>
        </DataTable>
    </div>
</template>
