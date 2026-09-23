<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';
import SubscriptionCard from '@/components/billing/SubscriptionCard.vue';
import PaymentProviderSelector from '@/components/billing/PaymentProviderSelector.vue';
import billingService from '@/services/billingService';

const router = useRouter();
const billingStore = useBillingStore();

const showPlansDialog = ref(false);
const showCancelDialog = ref(false);
const cancelImmediately = ref(false);

const selectedProvider = ref(null);
const availableProviders = ref([]);

const loading = computed(() => billingStore.loading);
const saving = computed(() => billingStore.saving);
const subscription = computed(() => billingStore.subscription);
const currentPlan = computed(() => billingStore.currentPlan);
const activePlans = computed(() => billingStore.activePlans);
const invoices = computed(() => billingStore.invoices);
const invoiceStats = computed(() => billingStore.invoiceStats);

const usageItems = computed(() => {
    const u = billingStore.usage || {};
    return {
        ai: { label: 'AI Requests', used: u.ai?.used || 0, limit: u.ai?.limit, percentage: u.ai?.percentage || 0 },
        agents: { label: 'Agents', used: u.agents?.used || 0, limit: u.agents?.limit, percentage: u.agents?.percentage || 0 },
        customers: { label: 'Customers', used: u.customers?.used || 0, limit: u.customers?.limit, percentage: u.customers?.percentage || 0 },
        widgets: { label: 'Widgets', used: u.widgets?.used || 0, limit: u.widgets?.limit, percentage: u.widgets?.percentage || 0 },
        storage: { label: 'Storage', used: u.storage?.used || 0, limit: u.storage?.limit, percentage: u.storage?.percentage || 0 },
        conversations: { label: 'Conversations', used: u.conversations?.used || 0, limit: u.conversations?.limit, percentage: u.conversations?.percentage || 0 }
    };
});

const loadData = async () => {
    await Promise.allSettled([billingStore.fetchSubscription(), billingStore.fetchPlans({ public: true }), billingStore.fetchInvoices({ per_page: 5 }), billingStore.fetchInvoiceStatistics(), billingStore.fetchUsage()]);
};

// todo; Old Code
// const handleSelectPlan = async (plan) => {
//     try {
//         if (subscription.value) {
//             const currentPrice = Number(currentPlan.value?.price_monthly || 0);
//             const newPrice = Number(plan.price_monthly || 0);

//             if (newPrice > currentPrice) {
//                 await billingStore.upgradeSubscription(plan.id);
//             } else {
//                 await billingStore.downgradeSubscription(plan.id);
//             }
//         } else {
//             await billingStore.createSubscription({
//                 plan_id: plan.id,
//                 billing_cycle: 'monthly',
//             });
//         }
//         showPlansDialog.value = false;
//         await loadData();
//     } catch (e) {
//         // toast already shown by store
//     }
// };

const handleSelectPlan = async (plan) => {
    try {
        const data = await billingStore.createCheckout(plan.id, 'monthly', selectedProvider.value);

        // Redirect to provider checkout
        window.location.href = data.checkout_url;
    } catch (e) {
        // error handled by store
    }
};

const handleCancel = async () => {
    try {
        await billingStore.cancelSubscription(cancelImmediately.value);
        showCancelDialog.value = false;
        await loadData();
    } catch (e) {
        console.error(e);
    }
};

const handleResume = async () => {
    try {
        await billingStore.resumeSubscription();
        await loadData();
    } catch (e) {}
};

const getProgressClass = (percentage) => {
    if (percentage >= 90) return 'progress-danger';
    if (percentage >= 70) return 'progress-warning';
    return 'progress-success';
};

const formatLimitLabel = (key) => {
    const labels = {
        'agents.max': 'Agents',
        'customers.max': 'Customers',
        'widgets.max': 'Widgets',
        'kb.articles.max': 'KB Articles',
        'conversations.monthly': 'Conversations',
        'ai.requests.monthly': 'AI Requests',
        'ai.tokens.monthly': 'AI Tokens',
        'storage.bytes': 'Storage'
    };
    return labels[key] || key;
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatCurrency = (amount, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);

// onMounted(loadData);
onMounted(async () => {
    loadData();
    const r = await billingService.getProviders();
    availableProviders.value = r.data.data;
    selectedProvider.value = availableProviders.value.find((p) => p.available)?.key || null;
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Billing & Subscription</h1>
            <p class="text-surface-600 dark:text-surface-400">Manage your subscription, invoices, and payments</p>
        </div>

        <div v-if="loading && !subscription" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2">
                    <SubscriptionCard :subscription="subscription">
                        <template #actions>
                            <Button label="Change Plan" icon="pi pi-refresh" @click="showPlansDialog = true" />
                            <Button v-if="subscription?.is_cancelled" label="Resume" icon="pi pi-play" severity="success" :loading="saving" @click="handleResume" />
                            <Button v-else label="Cancel" icon="pi pi-times" severity="danger" outlined @click="showCancelDialog = true" />
                        </template>
                        <template #empty-actions>
                            <Button label="Choose a Plan" icon="pi pi-plus" @click="showPlansDialog = true" />
                        </template>
                    </SubscriptionCard>
                </div>

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
                                        <span class="text-sm text-surface-500"> {{ item.used }} / {{ item.limit || '∞' }} </span>
                                    </div>
                                    <ProgressBar :value="item.percentage" :class="getProgressClass(item.percentage)" :showValue="false" style="height: 8px" />
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-surface-500">No usage data</div>
                        </template>
                    </Card>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">{{ invoiceStats.total || 0 }}</div>
                            <div class="text-sm text-surface-600">Total Invoices</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-success">{{ invoiceStats.paid || 0 }}</div>
                            <div class="text-sm text-surface-600">Paid</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-warning">{{ invoiceStats.open || 0 }}</div>
                            <div class="text-sm text-surface-600">Open</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-danger">{{ invoiceStats.overdue || 0 }}</div>
                            <div class="text-sm text-surface-600">Overdue</div>
                        </div>
                    </template></Card
                >
            </div>

            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-file text-primary"></i>
                            <span>Recent Invoices</span>
                        </div>
                        <Button label="View All" icon="pi pi-arrow-right" severity="secondary" text size="small" @click="router.push('/billing/invoices')" />
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
                            <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                        </Column>
                        <Column header="Actions" style="width: 100px">
                            <template #body="{ data }">
                                <Button icon="pi pi-eye" severity="info" text rounded @click="router.push(`/billing/invoices/${data.id}`)" />
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </template>

        <Dialog v-model:visible="showPlansDialog" header="Choose a Plan" :style="{ width: '900px' }" modal>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="plan in activePlans"
                    :key="plan.id"
                    class="border rounded-lg p-4 transition-all"
                    :class="{
                        'border-primary ring-2 ring-primary': currentPlan?.id === plan.id,
                        'border-surface-200 dark:border-surface-700 hover:border-primary': currentPlan?.id !== plan.id
                    }"
                >
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
                            <span>{{ formatLimitLabel(key) }}: {{ limit || '∞' }}</span>
                        </li>
                    </ul>

                    <Button :label="currentPlan?.id === plan.id ? 'Current Plan' : 'Select'" :disabled="currentPlan?.id === plan.id" :severity="currentPlan?.id === plan.id ? 'secondary' : 'primary'" class="w-full" @click="handleSelectPlan(plan)" />
                </div>
            </div>
            <Divider />
            <PaymentProviderSelector v-model="selectedProvider" :providers="availableProviders" />
        </Dialog>

        <Dialog v-model:visible="showCancelDialog" header="Cancel Subscription" :style="{ width: '450px' }" modal>
            <div class="space-y-4">
                <p>Are you sure you want to cancel your subscription?</p>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="cancelImmediately" binary />
                    <label class="text-sm">Cancel immediately (lose access now)</label>
                </div>
                <p v-if="!cancelImmediately" class="text-sm text-surface-500">You will retain access until {{ formatDate(subscription?.ends_at) }}</p>
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
    background: #10b981;
}

.progress-warning :deep(.p-progressbar-value) {
    background: #f59e0b;
}

.progress-danger :deep(.p-progressbar-value) {
    background: #ef4444;
}
</style>
