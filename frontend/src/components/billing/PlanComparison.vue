<!-- src/components/billing/PlanComparison.vue -->
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    plans: {
        type: Array,
        required: true,
    },
    currentPlanId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['select', 'update:yearly']);

const yearly = ref(false);

const maxSavings = computed(() => {
    return Math.max(...props.plans.map(p => p.yearly_savings_percentage || 0));
});

const isCurrent = (plan) => plan.id === props.currentPlanId;

const formatLimitLabel = (key) => {
    const labels = {
        ai_messages: 'AI Messages',
        agents: 'Team Members',
        documents: 'Documents',
        storage_bytes: 'Storage',
        conversations: 'Conversations',
    };
    return labels[key] || key;
};

const formatLimitValue = (key, value) => {
    if (key === 'storage_bytes') {
        return formatBytes(value);
    }
    if (value === 0 || value === -1) return 'Unlimited';
    return new Intl.NumberFormat().format(value) + '/month';
};

const formatBytes = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatFeatureLabel = (feature) => {
    const labels = {
        ai_chat: 'AI Chat',
        knowledge_base: 'Knowledge Base',
        ticket_system: 'Ticket System',
        analytics: 'Analytics',
        integrations: 'Integrations',
        custom_branding: 'Custom Branding',
        priority_support: 'Priority Support',
        sso: 'SSO / SAML',
        audit_logs: 'Audit Logs',
    };
    return labels[feature] || feature.replace(/_/g, ' ');
};
</script>

<template>
    <div class="plan-comparison">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card v-for="plan in plans" :key="plan.id" :class="{ 'ring-2 ring-primary': plan.is_default }">
                <template #title>
                    <div class="text-center">
                        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
                        <p class="text-sm text-surface-500 mt-1">{{ plan.description }}</p>
                        <div class="text-3xl font-bold text-primary my-3">
                            {{ plan.formatted_price_monthly }}
                            <span class="text-sm text-surface-500 font-normal">/mo</span>
                        </div>
                        <p v-if="plan.trial_days > 0" class="text-xs text-info">
                            {{ plan.trial_days }}-day free trial
                        </p>
                    </div>
                </template>
                <template #content>
                    <ul class="space-y-3">
                        <li v-for="(value, key) in plan.limits" :key="key" class="flex items-start gap-2 text-sm">
                            <i class="pi pi-check text-success mt-1"></i>
                            <div>
                                <div class="font-medium">{{ formatLimitLabel(key) }}</div>
                                <div class="text-surface-500">{{ formatLimitValue(key, value) }}</div>
                            </div>
                        </li>
                    </ul>

                    <Divider />

                    <ul class="space-y-2">
                        <li v-for="(enabled, feature) in plan.features" :key="feature"
                            class="flex items-center gap-2 text-sm">
                            <i :class="enabled ? 'pi pi-check text-success' : 'pi pi-times text-surface-300'"></i>
                            <span :class="{ 'text-surface-500': !enabled }">
                                {{ formatFeatureLabel(feature) }}
                            </span>
                        </li>
                    </ul>
                </template>
                <template #footer>
                    <Button :label="isCurrent(plan) ? 'Current Plan' : 'Choose ' + plan.name"
                        :disabled="isCurrent(plan)" class="w-full" :severity="isCurrent(plan) ? 'secondary' : 'primary'"
                        @click="$emit('select', plan)" />
                </template>
            </Card>
        </div>

        <!-- Yearly Toggle -->
        <div class="flex justify-center mt-6">
            <div class="inline-flex items-center gap-3 p-2 bg-surface-100 dark:bg-surface-800 rounded-lg">
                <span :class="{ 'font-bold': !yearly }">Monthly</span>
                <ToggleSwitch v-model="yearly" />
                <span :class="{ 'font-bold': yearly }">
                    Yearly
                    <Tag v-if="maxSavings > 0" :value="'Save up to ' + maxSavings + '%'" severity="success" size="small"
                        class="ml-1" />
                </span>
            </div>
        </div>
    </div>
</template>