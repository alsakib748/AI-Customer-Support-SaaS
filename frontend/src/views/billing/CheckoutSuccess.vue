<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const route = useRoute();
const billingStore = useBillingStore();

const sessionId = route.query.session_id || null;
const provider = route.query.provider || null;

const polling = ref(true);
const activated = ref(false);
const failed = ref(false);
let timer = null;
let attempts = 0;
const MAX_ATTEMPTS = 10;

const verify = async () => {
    let sub = null;
    try {
        sub = sessionId ? await billingStore.verifyCheckout(sessionId, provider) : await billingStore.fetchSubscription();
    } catch (e) {
        // keep polling — Stripe/verification can lag a moment
    }

    // verifyCheckout returns { paid: false, status } when the session isn't complete yet.
    if (sub && sub.paid === false) {
        attempts += 1;
    } else if (sub && ['active', 'trialing'].includes(sub.status)) {
        activated.value = true;
        polling.value = false;
        if (timer) clearInterval(timer);
        return;
    } else {
        attempts += 1;
    }

    if (attempts >= MAX_ATTEMPTS) {
        failed.value = true;
        polling.value = false;
        if (timer) clearInterval(timer);
    }
};

onMounted(() => {
    verify();
    timer = setInterval(() => {
        if (polling.value) verify();
    }, 2000);
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

        <h1 class="text-3xl font-bold mb-6">Payment Successful</h1>

        <div v-if="polling" class="mb-6">
            <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
            <p class="text-sm text-surface-500 mt-2">Activating your subscription...</p>
        </div>

        <div v-else-if="activated" class="mb-6">
            <i class="pi pi-check-circle text-4xl text-green-500"></i>
            <p class="text-green-600 font-medium mt-2">Subscription activated! Your plan has been updated.</p>
        </div>

        <div v-else-if="failed" class="mb-6">
            <p class="text-yellow-600">Still processing. You'll receive an email once it's ready.</p>
        </div>

        <Button label="Go to Billing" icon="pi pi-arrow-right" @click="$router.push('/billing')" />
    </div>
</template>
