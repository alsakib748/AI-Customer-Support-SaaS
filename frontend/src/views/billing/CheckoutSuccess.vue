<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useBillingStore } from '@/stores/billing';

const billingStore = useBillingStore();

const polling = ref(true);
const activated = ref(false);
let timer = null;

const check = async () => {
    try {
        const sub = await billingStore.fetchSubscription();
        if (sub && ['active', 'trialing'].includes(sub.status)) {
            activated.value = true;
            polling.value = false;
            if (timer) clearInterval(timer);
        }
    } catch (e) {
        // keep polling
    }
};

onMounted(() => {
    check();
    timer = setInterval(check, 3000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="p-6 max-w-2xl mx-auto text-center">
        <div class="mb-6">
            <i class="pi pi-check-circle text-6xl text-green-500"></i>
        </div>

        <h1 class="text-3xl font-bold mb-3">Payment Successful</h1>
        <p class="text-surface-600 mb-6">
            Your subscription is being activated. This usually takes a few seconds.
        </p>

        <div v-if="polling" class="mb-6">
            <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
            <p class="text-sm text-surface-500 mt-2">Waiting for confirmation...</p>
        </div>

        <div v-else-if="activated" class="mb-6">
            <p class="text-green-600 font-medium">
                ✓ Subscription activated!
            </p>
        </div>

        <div v-else class="mb-6">
            <p class="text-yellow-600">
                Still processing. You'll receive an email once it's ready.
            </p>
        </div>

        <Button label="Go to Billing" icon="pi pi-arrow-right" @click="$router.push('/billing')" />
    </div>
</template>