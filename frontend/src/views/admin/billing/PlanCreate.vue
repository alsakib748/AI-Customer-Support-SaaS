<script setup>
import { reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useBillingStore } from '@/stores/billing';

const router = useRouter();
const billingStore = useBillingStore();

const saving = computed(() => billingStore.saving);

const form = reactive({
    name: '',
    slug: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    currency: 'USD',
    trial_days: 14,
    is_active: true,
    is_public: true,
    features: {
        ai_chat: true,
        knowledge_base: true,
        ticket_system: true,
        analytics: true,
        integrations: false,
        custom_branding: true,
        priority_support: false
    },
    limits: {
        'agents.max': 5,
        'customers.max': 1000,
        'widgets.max': 2,
        'kb.articles.max': 100,
        'conversations.monthly': 5000,
        'ai.requests.monthly': 2000,
        'ai.tokens.monthly': 1000000,
        'storage.bytes': 5368709120
    }
});

const handleSubmit = async () => {
    try {
        await billingStore.createAdminPlan(form);
        router.push('/admin/billing/plans');
    } catch (e) {
        console.error(e);
    }
};
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Create Plan</h1>
                <p class="text-surface-600">Add a new subscription plan</p>
            </div>
            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/admin/billing/plans')" />
        </div>

        <Card>
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Name *</label>
                        <InputText v-model="form.name" placeholder="e.g. Professional" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Slug</label>
                        <InputText v-model="form.slug" placeholder="auto-generated if empty" />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="text-sm font-medium">Description</label>
                        <Textarea v-model="form.description" rows="2" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Monthly Price</label>
                        <InputNumber v-model="form.price_monthly" mode="currency" currency="USD" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Yearly Price</label>
                        <InputNumber v-model="form.price_yearly" mode="currency" currency="USD" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Trial Days</label>
                        <InputNumber v-model="form.trial_days" :min="0" :max="90" />
                    </div>
                    <div class="flex items-center gap-4 pt-6">
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.is_active" binary inputId="is_active" />
                            <label for="is_active">Active</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.is_public" binary inputId="is_public" />
                            <label for="is_public">Public</label>
                        </div>
                    </div>
                </div>

                <Divider />

                <h3 class="font-semibold mb-3">Limits</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="(value, key) in form.limits" :key="key" class="flex flex-col gap-2">
                        <label class="text-sm font-medium">{{ key }}</label>
                        <InputNumber v-model="form.limits[key]" :min="0" />
                    </div>
                </div>

                <Divider />

                <h3 class="font-semibold mb-3">Features</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="(enabled, key) in form.features" :key="key" class="flex items-center gap-2">
                        <Checkbox v-model="form.features[key]" binary :inputId="`feature-${key}`" />
                        <label :for="`feature-${key}`">{{ key }}</label>
                    </div>
                </div>

                <Divider />

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" outlined @click="router.push('/admin/billing/plans')" />
                    <Button label="Create Plan" icon="pi pi-check" :loading="saving" @click="handleSubmit" />
                </div>
            </template>
        </Card>
    </div>
</template>
