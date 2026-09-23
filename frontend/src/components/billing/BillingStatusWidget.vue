<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const subscription = computed(() => billingStore.subscription);
const planName = computed(() => billingStore.planName);

const statusIcon = computed(() => {
    if (billingStore.isTrialing) return 'pi pi-clock';
    if (billingStore.isCancelled) return 'pi pi-times-circle';
    if (billingStore.isExpired) return 'pi pi-exclamation-circle';
    if (billingStore.isActive) return 'pi pi-check-circle';
    return 'pi pi-credit-card';
});

const statusColor = computed(() => {
    if (billingStore.isTrialing) return '#3B82F6';
    if (billingStore.isCancelled) return '#EF4444';
    if (billingStore.isExpired) return '#EF4444';
    if (billingStore.isActive) return '#10B981';
    return '#6B7280';
});

const warningText = computed(() => {
    if (billingStore.isTrialing) return `Trial: ${billingStore.trialDaysRemaining}d left`;
    if (billingStore.isCancelled) return 'Cancelled';
    if (billingStore.isExpired) return 'Expired';
    if (billingStore.daysRemaining && billingStore.daysRemaining <= 7) {
        return `Renews in ${billingStore.daysRemaining}d`;
    }
    return null;
});

const goToBilling = () => router.push('/billing');
</script>

<template>
    <div v-if="subscription" class="billing-widget">
        <div class="flex items-center gap-2 px-3 py-1 rounded-lg cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800" @click="goToBilling">
            <i :class="statusIcon" :style="{ color: statusColor }"></i>
            <div class="flex flex-col">
                <span class="text-xs font-medium">{{ planName }}</span>
                <span v-if="warningText" class="text-xs text-surface-500">{{ warningText }}</span>
            </div>
        </div>
    </div>
</template>
