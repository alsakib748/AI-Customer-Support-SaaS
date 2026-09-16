<!-- src/components/billing/SubscriptionCard.vue -->
<script setup>
const props = defineProps({
    subscription: { type: Object, default: null },
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};
</script>

<template>
    <Card>
        <template #title>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="pi pi-credit-card text-primary"></i>
                    <span>Current Subscription</span>
                </div>
                <Tag v-if="subscription" :value="subscription.status_label" :severity="subscription.status_color" />
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
                        <Tag :value="subscription.auto_renew ? 'Enabled' : 'Disabled'"
                            :severity="subscription.auto_renew ? 'success' : 'warning'" />
                    </div>
                </div>

                <div v-if="subscription.is_trialing" class="p-3 bg-info-50 dark:bg-info-950 rounded-lg mb-4">
                    <i class="pi pi-clock text-info mr-2"></i>
                    Trial ends in {{ subscription.trial_days_remaining }} days
                </div>

                <div v-if="subscription.is_cancelled" class="p-3 bg-warning-50 dark:bg-warning-950 rounded-lg mb-4">
                    <i class="pi pi-exclamation-triangle text-warning mr-2"></i>
                    Cancelled. Access until {{ formatDate(subscription.ends_at) }}
                </div>

                <div class="flex gap-2 flex-wrap">
                    <slot name="actions" />
                </div>
            </div>
            <div v-else class="text-center py-8">
                <i class="pi pi-credit-card text-6xl text-surface-300 mb-4"></i>
                <h3 class="text-lg font-medium mb-2">No Active Subscription</h3>
                <p class="text-surface-500 mb-4">Choose a plan to get started</p>
                <slot name="empty-actions" />
            </div>
        </template>
    </Card>
</template>