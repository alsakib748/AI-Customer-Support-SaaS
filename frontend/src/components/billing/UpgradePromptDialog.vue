<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    feature: { type: String, default: null },
    limit: { type: Number, default: null },
    current: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);
const router = useRouter();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const featureLabel = computed(() => {
    const labels = {
        'agents.max': 'team members',
        'customers.max': 'customers',
        'widgets.max': 'chat widgets',
        'kb.articles.max': 'knowledge base articles',
        'conversations.monthly': 'conversations this month',
        'ai.requests.monthly': 'AI requests this month',
        'ai.tokens.monthly': 'AI tokens this month',
    };
    return labels[props.feature] || 'resources';
});

const goToPlans = () => {
    visible.value = false;
    router.push('/billing/plans');
};
</script>


<template>
    <Dialog v-model:visible="visible" header="Upgrade Required" :style="{ width: '450px' }" modal :draggable="false">
        <div class="flex flex-col items-center text-center space-y-4 py-4">
            <div class="w-20 h-20 rounded-full bg-warning-50 dark:bg-warning-950 flex items-center justify-center">
                <i class="pi pi-exclamation-triangle text-4xl text-warning"></i>
            </div>

            <h2 class="text-xl font-bold">
                You've reached your plan limit
            </h2>

            <p class="text-surface-600 dark:text-surface-400">
                You've used <strong>{{ current }}</strong> of your
                <strong>{{ limit }}</strong> {{ featureLabel }} allowed on your
                current plan.
            </p>

            <p class="text-surface-500 text-sm">
                Upgrade your plan to add more.
            </p>
        </div>

        <template #footer>
            <Button label="Maybe Later" severity="secondary" outlined @click="visible = false" />
            <Button label="View Plans" icon="pi pi-arrow-right" @click="goToPlans" />
        </template>
    </Dialog>
</template>
