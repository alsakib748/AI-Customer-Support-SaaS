<!-- src/views/billing/BillingOverview.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';
// import { useToast } from 'primevue/usetoast';

const router = useRouter();
const billingStore = useBillingStore();
// const toast = useToast();

const showPlansDialog = ref(false);
const showCancelDialog = ref(false);
const cancelImmediately = ref(false);

const loading = computed(() => billingStore.loading);
const saving = computed(() => billingStore.saving);
const subscription = computed(() => billingStore.subscription);
const currentPlan = computed(() => billingStore.currentPlan);
const plans = computed(() => billingStore.plans);
const activePlans = computed(() => billingStore.activePlans);
const invoices = computed(() => billingStore.invoices);
const invoiceStats = computed(() => billingStore.invoiceStats);

const usageItems = computed(() => {
    const u = subscription.value?.usage || {};
    return {
        ai: { label: 'AI Messages', used: u.ai?.used || 0, limit: u.ai?.limit, percentage: u.ai?.percentage || 0 },
        agents: { label: 'Agents', used: u.agents?.used || 0, limit: u.agents?.limit, percentage: u.agents?.percentage || 0 },
        documents: { label: 'Documents', used: u.documents?.used || 0, limit: u.documents?.limit, percentage: u.documents?.percentage || 0 },
        storage: { label: 'Storage', used: u.storage?.used || 0, limit: u.storage?.limit, percentage: u.storage?.percentage || 0 },
        conversations: { label: 'Conversations', used: u.conversations?.used || 0, limit: u.conversations?.limit, percentage: u.conversations?.percentage || 0 },
    };
});

const loadData = async () => {
    await Promise.all([
        billingStore.fetchSubscription(),
        billingStore.fetchPlans({ public: true }),
        billingStore.fetchInvoices({ per_page: 5 }),
        billingStore.fetchInvoiceStatistics(),
    ]);
};

const handleSelectPlan = async (plan) => {
    if (subscription.value) {
        // Upgrade or downgrade
        if (plan.price_monthly > currentPlan.value.price_monthly) {
            await billingStore.upgradeSubscription(plan.id);
        } else {
            await billingStore.downgradeSubscription(plan.id);
        }
    } else {
        // Create new subscription
        await billingStore.createSubscription({
            plan_id: plan.id,
            billing_cycle: 'monthly',
        });
    }
    showPlansDialog.value = false;
    await loadData();
};

const handleCancel = async () => {
    await billingStore.cancelSubscription(cancelImmediately.value);
    showCancelDialog.value = false;
    await loadData();
};

const handleResume = async () => {
    await billingStore.resumeSubscription();
    await loadData();
};

const getProgressClass = (percentage) => {
    if (percentage >= 90) return 'progress-danger';
    if (percentage >= 70) return 'progress-warning';
    return 'progress-success';
};

const formatLimitLabel = (key) => {
    const labels = {
        ai_messages: 'AI Messages',
        agents: 'Agents',
        documents: 'Documents',
        storage_bytes: 'Storage',
        conversations: 'Conversations',
    };
    return labels[key] || key;
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
</script>

<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Billing & Subscription</h1>
            <p class="text-surface-600 dark:text-surface-400">Manage your subscription, invoices, and payments</p>
        </div>

        <!-- Loading -->
        <div v-if="loading && !subscription" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else>
            <!-- Current Subscription Card -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2">
                    <Card>
                        <template #title>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-credit-card text-primary"></i>
                                    <span>Current Subscription</span>
                                </div>
                                <Tag v-if="subscription" :value="subscription.status_label"
                                    :severity="subscription.status_color" />
                            </div>
                        </template>
                        <template #content>
                            <div v-if="subscription">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="text-sm text-surface-500">Plan</label>
                                        <div class="text-xl font-bold">{{ subscription.plan?.name || 'No Plan' }}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-surface-500">Billing Cycle</label>
                                        <div class="text-xl font-bold capitalize">{{ subscription.billing_cycle }}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-surface-500">Next Billing</label>
                                        <div class="font-medium">{{ formatDate(subscription.next_billing_at) }}</div>
                                    </div>
                                    <div>
                                        <label class="text-sm text-surface-500">Auto Renew</label>
                                        <div class="font-medium">
                                            <Tag :value="subscription.auto_renew ? 'Enabled' : 'Disabled'"
                                                :severity="subscription.auto_renew ? 'success' : 'warning'" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Trial Info -->
                                <div v-if="subscription.is_trialing"
                                    class="p-3 bg-info-50 dark:bg-info-950 rounded-lg mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-clock text-info"></i>
                                        <span class="font-medium">
                                            Trial ends in {{ subscription.trial_days_remaining }} days
                                        </span>
                                    </div>
                                </div>

                                <!-- Cancellation Warning -->
                                <div v-if="subscription.is_cancelled"
                                    class="p-3 bg-warning-50 dark:bg-warning-950 rounded-lg mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-exclamation-triangle text-warning"></i>
                                        <span class="font-medium">
                                            Subscription cancelled. Access until {{ formatDate(subscription.ends_at) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2 flex-wrap">
                                    <Button label="Change Plan" icon="pi pi-refresh" severity="primary"
                                        @click="showPlansDialog = true" />
                                    <Button v-if="subscription.is_cancelled" label="Resume" icon="pi pi-play"
                                        severity="success" @click="handleResume" :loading="saving" />
                                    <Button v-else label="Cancel" icon="pi pi-times" severity="danger" outlined
                                        @click="showCancelDialog = true" />
                                </div>
                            </div>

                            <!-- No Subscription -->
                            <div v-else class="text-center py-8">
                                <i class="pi pi-credit-card text-6xl text-surface-300 mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No Active Subscription</h3>
                                <p class="text-surface-500 mb-4">Choose a plan to get started</p>
                                <Button label="Choose a Plan" icon="pi pi-plus" severity="primary"
                                    @click="showPlansDialog = true" />
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Usage Summary -->
                <div class="lg:col-span-1">
                    <Card>
                        <template #title>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-chart-bar text-primary"></i>
                                <span>Usage</span>
                            </div>
                        </template>
                        <template #content>
                            <div v-if="subscription" class="space-y-4">
                                <div v-for="(item, key) in usageItems" :key="key">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">{{ item.label }}</span>
                                        <span class="text-sm text-surface-500">
                                            {{ item.used }} / {{ item.limit || '∞' }}
                                        </span>
                                    </div>
                                    <ProgressBar :value="item.percentage" :class="getProgressClass(item.percentage)"
                                        :showValue="false" style="height: 8px" />
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-surface-500">
                                No usage data
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">{{ invoiceStats.total || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Total Invoices</div>
                        </div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-success">{{ invoiceStats.paid || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Paid</div>
                        </div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-warning">{{ invoiceStats.open || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Open</div>
                        </div>
                    </template>
                </Card>
                <Card>
                    <template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-danger">{{ invoiceStats.overdue || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Overdue</div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Recent Invoices -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-file text-primary"></i>
                            <span>Recent Invoices</span>
                        </div>
                        <Button label="View All" icon="pi pi-arrow-right" severity="secondary" text size="small"
                            @click="$router.push('/billing/invoices')" />
                    </div>
                </template>
                <template #content>
                    <DataTable :value="invoices.slice(0, 5)" :loading="loading" class="w-full">
                        <Column field="invoice_number" header="Invoice #" />
                        <Column field="total" header="Amount">
                            <template #body="{ data }">
                                {{ formatCurrency(data.total, data.currency) }}
                            </template>
                        </Column>
                        <Column field="status" header="Status">
                            <template #body="{ data }">
                                <Tag :value="data.status_label" :severity="data.status_color" />
                            </template>
                        </Column>
                        <Column field="created_at" header="Date">
                            <template #body="{ data }">
                                {{ formatDate(data.created_at) }}
                            </template>
                        </Column>
                        <Column header="Actions" style="width: 100px">
                            <template #body="{ data }">
                                <Button icon="pi pi-eye" severity="info" text rounded
                                    @click="$router.push(`/billing/invoices/${data.id}`)" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </template>

        <!-- Plans Dialog -->
        <Dialog v-model:visible="showPlansDialog" header="Choose a Plan" :style="{ width: '900px' }" modal>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div v-for="plan in activePlans" :key="plan.id" class="border rounded-lg p-4 transition-all" :class="{
                    'border-primary ring-2 ring-primary': currentPlan?.id === plan.id,
                    'border-surface-200 dark:border-surface-700 hover:border-primary': currentPlan?.id !== plan.id,
                }">
                    <div class="text-center mb-4">
                        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
                        <div class="text-3xl font-bold text-primary my-2">
                            {{ plan.formatted_price_monthly }}
                            <span class="text-sm text-surface-500">/mo</span>
                        </div>
                        <p class="text-sm text-surface-500">{{ plan.description }}</p>
                    </div>

                    <ul class="space-y-2 mb-4">
                        <li v-for="(limit, key) in plan.limits" :key="key" class="flex items-center gap-2 text-sm">
                            <i class="pi pi-check text-success"></i>
                            <span>{{ formatLimitLabel(key) }}: {{ limit }}</span>
                        </li>
                    </ul>

                    <Button :label="currentPlan?.id === plan.id ? 'Current Plan' : 'Select'"
                        :disabled="currentPlan?.id === plan.id"
                        :severity="currentPlan?.id === plan.id ? 'secondary' : 'primary'" class="w-full"
                        @click="handleSelectPlan(plan)" />
                </div>
            </div>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:visible="showCancelDialog" header="Cancel Subscription" :style="{ width: '450px' }" modal>
            <div class="space-y-4">
                <p>Are you sure you want to cancel your subscription?</p>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="cancelImmediately" binary />
                    <label class="text-sm">Cancel immediately (lose access now)</label>
                </div>
                <p v-if="!cancelImmediately" class="text-sm text-surface-500">
                    You will retain access until {{ formatDate(subscription?.ends_at) }}
                </p>
            </div>
            <template #footer>
                <Button label="Keep Subscription" severity="secondary" @click="showCancelDialog = false" />
                <Button label="Cancel Subscription" severity="danger" :loading="saving" @click="handleCancel" />
            </template>
        </Dialog>

        <Toast />
    </div>
</template>

<style scoped>
.progress-success :deep(.p-progressbar-value) {
    background: #10B981;
}

.progress-warning :deep(.p-progressbar-value) {
    background: #F59E0B;
}

.progress-danger :deep(.p-progressbar-value) {
    background: #EF4444;
}
</style>