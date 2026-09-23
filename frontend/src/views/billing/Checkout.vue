<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';
import PaymentProviderSelector from '@/components/billing/PaymentProviderSelector.vue';
import billingService from '@/services/billingService';

const route = useRoute();
const router = useRouter();
const billingStore = useBillingStore();

const plan = ref(null);
const providers = ref([]);
const selectedProvider = ref(null);
const loading = ref(false);
const submitting = ref(false);

const loadData = async () => {
    loading.value = true;
    try {
        const [planRes, providersRes] = await Promise.all([billingService.getPlan(route.params.planId), billingService.getProviders()]);

        if (planRes.data.success) plan.value = planRes.data.data;
        if (providersRes.data.success) {
            providers.value = providersRes.data.data;
            selectedProvider.value = providers.value.find((p) => p.available)?.key || null;
        }
    } catch (e) {
        console.error('Failed to load checkout data:', e);
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    if (!selectedProvider.value) return;
    submitting.value = true;
    try {
        const data = await billingStore.createCheckout(plan.value.id, route.query.cycle || 'monthly', selectedProvider.value);

        // Redirect to provider's hosted checkout
        window.location.href = data.checkout_url;
    } catch (e) {
        // toast already shown by store
    } finally {
        submitting.value = false;
    }
};

const cancel = () => router.push('/billing/plans');

onMounted(loadData);
</script>

<template>
    <div class="p-6 max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Complete Your Subscription</h1>
            <p class="text-surface-600">Choose a payment method to continue</p>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>

        <template v-else>
            <Card class="mb-6">
                <template #title>Selected Plan</template>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xl font-bold">{{ plan?.name }}</div>
                            <div class="text-sm text-surface-500">{{ plan?.description }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-primary">
                                {{ plan?.formatted_price_monthly || plan?.price_monthly }}
                                <span class="text-sm text-surface-500">/mo</span>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <Card class="mb-6">
                <template #title>Payment Method</template>
                <template #content>
                    <PaymentProviderSelector v-model="selectedProvider" :providers="providers" />
                </template>
            </Card>

            <div class="flex justify-end gap-2">
                <Button label="Cancel" severity="secondary" outlined @click="cancel" />
                <Button label="Continue to Payment" icon="pi pi-arrow-right" :disabled="!selectedProvider" :loading="submitting" @click="submit" />
            </div>
        </template>

        <Toast />
    </div>
</template>
