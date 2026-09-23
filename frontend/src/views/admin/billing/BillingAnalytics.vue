<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const loading = computed(() => billingStore.loading);
const analytics = computed(() => billingStore.analytics);

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);

onMounted(() => billingStore.fetchAdminAnalytics());
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Billing Analytics</h1>
                <p class="text-surface-600">Platform-wide billing insights</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/admin/billing')" />
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <div v-else-if="analytics" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-primary">{{ formatCurrency(analytics.mrr) }}</div>
                            <div class="text-sm text-surface-600">MRR</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-success">{{ formatCurrency(analytics.arr) }}</div>
                            <div class="text-sm text-surface-600">ARR</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-info">{{ analytics.active_subscriptions }}</div>
                            <div class="text-sm text-surface-600">Active</div>
                        </div>
                    </template></Card
                >
                <Card
                    ><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-warning">{{ analytics.churn_rate }}%</div>
                            <div class="text-sm text-surface-600">Churn Rate</div>
                        </div>
                    </template></Card
                >
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card>
                    <template #title>Revenue Breakdown</template>
                    <template #content>
                        <ul class="space-y-3">
                            <li class="flex justify-between">
                                <span>Revenue (30 days)</span>
                                <span class="font-bold text-success">{{ formatCurrency(analytics.revenue_last_30_days) }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>MRR</span>
                                <span class="font-bold">{{ formatCurrency(analytics.mrr) }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>ARR</span>
                                <span class="font-bold">{{ formatCurrency(analytics.arr) }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Payment Failures (30d)</span>
                                <span class="font-bold text-danger">{{ analytics.payment_failures_last_30_days }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>

                <Card>
                    <template #title>Subscription Breakdown</template>
                    <template #content>
                        <ul class="space-y-3">
                            <li class="flex justify-between">
                                <span>Active</span><span class="font-bold text-success">{{ analytics.active_subscriptions }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Trialing</span><span class="font-bold text-info">{{ analytics.trialing_tenants }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Past Due</span><span class="font-bold text-warning">{{ analytics.past_due }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Cancelled</span><span class="font-bold text-danger">{{ analytics.cancelled }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span>Expired</span><span class="font-bold text-danger">{{ analytics.expired }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>
            </div>

            <Card>
                <template #title>Plan Distribution</template>
                <template #content>
                    <div class="space-y-3">
                        <div v-for="plan in analytics.plan_distribution" :key="plan.plan_id" class="flex items-center justify-between">
                            <span class="font-medium">{{ plan.plan_name }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-surface-500">{{ plan.count }} subscribers</span>
                                <div class="w-32 bg-surface-200 dark:bg-surface-700 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" :style="{ width: (plan.count / Math.max(...analytics.plan_distribution.map((p) => p.count), 1)) * 100 + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>
