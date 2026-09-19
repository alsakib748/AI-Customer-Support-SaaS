<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const loading = computed(() => billingStore.loading);
const analytics = computed(() => billingStore.analytics);

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);

onMounted(() => billingStore.fetchAdminAnalytics());
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Billing Dashboard</h1>
            <p class="text-surface-600">Platform-wide billing overview</p>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <div v-else-if="analytics" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-primary">{{ formatCurrency(analytics.mrr) }}</div>
                            <div class="text-sm text-surface-600">MRR</div>
                        </div>
                    </template></Card>
                <Card><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-success">{{ formatCurrency(analytics.arr) }}</div>
                            <div class="text-sm text-surface-600">ARR</div>
                        </div>
                    </template></Card>
                <Card><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-info">{{ analytics.active_subscriptions }}</div>
                            <div class="text-sm text-surface-600">Active Subscriptions</div>
                        </div>
                    </template></Card>
                <Card><template #content>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-warning">{{ analytics.churn_rate }}%</div>
                            <div class="text-sm text-surface-600">Churn Rate</div>
                        </div>
                    </template></Card>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <template #title>Subscription Status</template>
                    <template #content>
                        <ul class="space-y-2">
                            <li class="flex justify-between"><span>Trialing</span><span class="font-bold">{{
                                    analytics.trialing_tenants }}</span></li>
                            <li class="flex justify-between"><span>Past Due</span><span
                                    class="font-bold text-warning">{{ analytics.past_due }}</span></li>
                            <li class="flex justify-between"><span>Cancelled</span><span
                                    class="font-bold text-danger">{{ analytics.cancelled }}</span></li>
                            <li class="flex justify-between"><span>Expired</span><span class="font-bold text-danger">{{
                                    analytics.expired }}</span></li>
                        </ul>
                    </template>
                </Card>

                <Card>
                    <template #title>This Month</template>
                    <template #content>
                        <ul class="space-y-2">
                            <li class="flex justify-between"><span>New Subscriptions</span><span
                                    class="font-bold text-success">{{ analytics.new_subscriptions }}</span></li>
                            <li class="flex justify-between"><span>Churned</span><span class="font-bold text-danger">{{
                                    analytics.churned_subscriptions }}</span></li>
                            <li class="flex justify-between"><span>Revenue (30d)</span><span class="font-bold">{{
                                formatCurrency(analytics.revenue_last_30_days) }}</span></li>
                            <li class="flex justify-between"><span>Payment Failures</span><span
                                    class="font-bold text-danger">{{ analytics.payment_failures_last_30_days }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>

                <Card>
                    <template #title>Plan Distribution</template>
                    <template #content>
                        <ul class="space-y-2">
                            <li v-for="p in analytics.plan_distribution" :key="p.plan_id" class="flex justify-between">
                                <span>{{ p.plan_name }}</span>
                                <span class="font-bold">{{ p.count }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>
            </div>

            <div class="flex gap-2 flex-wrap">
                <Button label="Manage Plans" icon="pi pi-box" @click="router.push('/admin/billing/plans')" />
                <Button label="Subscriptions" icon="pi pi-users" outlined
                    @click="router.push('/admin/billing/subscriptions')" />
                <Button label="Invoices" icon="pi pi-file" outlined @click="router.push('/admin/billing/invoices')" />
                <Button label="Payments" icon="pi pi-money-bill" outlined
                    @click="router.push('/admin/billing/payments')" />
                <Button label="Coupons" icon="pi pi-ticket" outlined @click="router.push('/admin/billing/coupons')" />
                <Button label="Analytics" icon="pi pi-chart-pie" outlined
                    @click="router.push('/admin/billing/analytics')" />
            </div>
        </div>
    </div>
</template>
