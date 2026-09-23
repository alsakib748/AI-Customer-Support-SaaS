<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const billingCycle = ref('monthly');
const loading = ref(false);

const plans = computed(() => billingStore.plans);
const currentPlan = computed(() => billingStore.currentPlan);
const subscription = computed(() => billingStore.subscription);

const activePlans = computed(() => plans.value.filter((p) => p.is_active !== false && p.is_public !== false));

const cycleOptions = [
    { label: 'Monthly', value: 'monthly' },
    { label: 'Yearly', value: 'yearly' }
];

const FEATURE_LABELS = {
    ai_chat: 'AI Chat',
    knowledge_base: 'Knowledge Base',
    ticket_system: 'Ticket System',
    analytics: 'Analytics',
    integrations: 'Integrations',
    custom_branding: 'Custom Branding',
    priority_support: 'Priority Support'
};

const LIMIT_LABELS = {
    'agents.max': 'Agents',
    'customers.max': 'Customers',
    'widgets.max': 'Widgets',
    'kb.articles.max': 'KB Articles',
    'conversations.monthly': 'Conversations / month',
    'ai.requests.monthly': 'AI Requests / month',
    'ai.tokens.monthly': 'AI Tokens / month',
    'storage.bytes': 'Storage'
};

const LIMIT_ORDER = ['agents.max', 'customers.max', 'widgets.max', 'kb.articles.max', 'conversations.monthly', 'ai.requests.monthly', 'ai.tokens.monthly', 'storage.bytes'];

const FEATURE_ORDER = ['ai_chat', 'knowledge_base', 'ticket_system', 'analytics', 'integrations', 'custom_branding', 'priority_support'];

const humanize = (key) =>
    key
        .split(/[._]/)
        .filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');

const labelFor = (map, key) => map[key] || humanize(key);

const unionKeys = (source) => {
    const present = new Set();
    activePlans.value.forEach((p) => Object.keys(p[source] || {}).forEach((k) => present.add(k)));
    const order = (source === 'limits' ? LIMIT_ORDER : FEATURE_ORDER).filter((k) => present.has(k));
    const extra = [...present].filter((k) => !order.includes(k)).sort();
    return [...order, ...extra];
};

const formatCurrency = (amount, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount || 0);

const formatBytes = (bytes) => {
    if (!bytes) return null;
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const planPrice = (plan) => {
    if (billingCycle.value === 'yearly') {
        return plan.formatted_price_yearly || formatCurrency(plan.price_yearly, plan.currency);
    }
    return plan.formatted_price_monthly || formatCurrency(plan.price_monthly, plan.currency);
};

const formatLimit = (key, value) => {
    if (value === null || value === undefined || value === 0 || value === '0' || value === '') {
        return 'Unlimited';
    }
    if (key === 'storage.bytes') {
        return formatBytes(value);
    }
    return new Intl.NumberFormat().format(value);
};

const isCurrentPlan = (plan) => plan.id === currentPlan.value?.id || plan.id === subscription.value?.plan?.id;

const comparisonLimitRows = computed(() =>
    unionKeys('limits').map((key) => {
        const values = {};
        activePlans.value.forEach((p) => {
            values[p.id] = formatLimit(key, p.limits?.[key]);
        });
        return { type: 'limit', key, label: labelFor(LIMIT_LABELS, key), values };
    })
);

const comparisonFeatureRows = computed(() =>
    unionKeys('features').map((key) => {
        const values = {};
        activePlans.value.forEach((p) => {
            values[p.id] = p.features?.[key] ? 'yes' : 'no';
        });
        return { type: 'feature', key, label: labelFor(FEATURE_LABELS, key), values };
    })
);

const comparisonRows = computed(() => [...comparisonLimitRows.value, ...comparisonFeatureRows.value]);

const selectPlan = (plan) => router.push({ path: `/billing/checkout/${plan.id}`, query: { cycle: billingCycle.value } });

const yearlySavingsLabel = (plan) => (plan.yearly_savings_percentage ? `Save ${plan.yearly_savings_percentage}%` : null);

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.allSettled([billingStore.fetchPlans({ public: true }), billingStore.fetchSubscription()]);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold">Plans</h1>
                <p class="text-surface-600">Compare and choose your plan.</p>
            </div>
            <div class="flex items-center gap-3">
                <SelectButton v-model="billingCycle" :options="cycleOptions" optionLabel="label" optionValue="value" />
                <Button label="Back to Billing" icon="pi pi-arrow-left" severity="secondary" outlined
                    @click="router.push('/billing')" />
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else>
            <div v-if="activePlans.length === 0" class="text-center py-12">
                <p class="text-surface-500">No plans are available right now.</p>
            </div>

            <template v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                    <Card v-for="plan in activePlans" :key="plan.id" class="relative">
                        <template #content>
                            <Tag v-if="plan.badge" :value="plan.badge" class="absolute top-3 right-3 mb-2" />
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-xl font-bold">{{ plan.name }}</h3>
                                <Tag v-if="plan.is_default" value="Default" severity="secondary" />
                            </div>
                            <p class="text-sm text-surface-600 min-h-10">{{ plan.description }}</p>

                            <div class="mt-4 mb-5">
                                <div class="flex items-end gap-2">
                                    <span class="text-3xl font-bold text-primary">{{ planPrice(plan) }}</span>
                                    <span class="text-sm text-surface-500 pb-1"> /{{ billingCycle === 'yearly' ? 'year'
                                        : 'month' }} </span>
                                    <span v-if="billingCycle === 'yearly'"
                                        class="text-xs font-semibold text-success mb-2">
                                        {{ yearlySavingsLabel(plan) }}
                                    </span>
                                </div>
                                <div v-if="billingCycle === 'yearly' && plan.formatted_price_monthly !== plan.formatted_price_yearly"
                                    class="text-xs text-surface-500">{{ plan.formatted_price_monthly }}/mo billed yearly
                                </div>
                            </div>

                            <ul class="space-y-2 mb-6">
                                <li v-for="(limit, key) in plan.limits" :key="key"
                                    class="flex items-center gap-2 text-sm">
                                    <i class="pi pi-check text-success"></i>
                                    <span>{{ labelFor(LIMIT_LABELS, key) }}: {{ formatLimit(key, limit) }}</span>
                                </li>
                                <template v-for="(enabled, key) in plan.features" :key="key">
                                    <li v-if="enabled" class="flex items-center gap-2 text-sm">
                                        <i class="pi pi-check text-success"></i>
                                        <span>{{ labelFor(FEATURE_LABELS, key) }}</span>
                                    </li>
                                </template>
                            </ul>

                            <Button :label="isCurrentPlan(plan) ? 'Current Plan' : 'Select Plan'"
                                :disabled="isCurrentPlan(plan)"
                                :severity="isCurrentPlan(plan) ? 'secondary' : 'primary'" icon="pi pi-arrow-right"
                                iconPos="right" class="w-full" @click="selectPlan(plan)" />
                            <div v-if="plan.trial_days > 0 && !isCurrentPlan(plan)"
                                class="text-center text-xs text-surface-500 mt-2">{{
                                    plan.trial_days }} day free trial available</div>
                        </template>
                    </Card>
                </div>

                <Card>
                    <template #title>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-table text-primary"></i>
                            <span>Compare Plans</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-surface-200 dark:border-surface-700">
                                        <th class="text-left font-semibold p-3 w-56">Feature</th>
                                        <th v-for="plan in activePlans" :key="plan.id" class="p-3 text-center">
                                            <div class="font-bold">{{ plan.name }}</div>
                                            <div class="text-primary font-bold">{{ planPrice(plan) }}</div>
                                            <div class="text-xs text-surface-500 capitalize">/{{ billingCycle ===
                                                'yearly' ? 'year' : 'month' }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="row in comparisonRows" :key="row.type + '-' + row.key">
                                        <tr v-if="row.type === 'limit'"
                                            class="border-b border-surface-100 dark:border-surface-800">
                                            <td class="p-3 font-medium text-surface-700 dark:text-surface-300">
                                                {{ row.label }}
                                            </td>
                                            <td v-for="plan in activePlans" :key="plan.id" class="p-3 text-center">
                                                {{ row.values[plan.id] }}
                                            </td>
                                        </tr>
                                        <tr v-if="row.type === 'feature'"
                                            class="border-b border-surface-100 dark:border-surface-800">
                                            <td class="p-3 font-medium text-surface-700 dark:text-surface-300">
                                                {{ row.label }}
                                            </td>
                                            <td v-for="plan in activePlans" :key="plan.id" class="p-3 text-center">
                                                <i v-if="row.values[plan.id] === 'yes'"
                                                    class="pi pi-check text-success"></i>
                                                <span v-else class="text-surface-400">—</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </Card>
            </template>
        </template>

        <Toast />
    </div>
</template>
