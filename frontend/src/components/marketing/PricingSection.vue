<script setup>
import { ref, computed, onMounted } from 'vue';
import SectionShell from '@/components/common/SectionShell.vue';
import SectionHeading from '@/components/common/SectionHeading.vue';
import PricingCard from '@/components/common/PricingCard.vue';
import { plans as fallbackPlans } from '@/data/marketing';
import { useBillingStore } from '@/stores/billing';

defineProps({
    // On the dedicated /pricing page the page heading is the H1, so this one is an H2.
    eyebrow: { type: String, default: 'Simple, transparent pricing' },
    title: { type: String, default: 'A plan for every stage of growth.' },
    lead: { type: String, default: 'Start free, upgrade when your support volume grows.' },
    headingTag: { type: String, default: 'h2' }
});

const billingStore = useBillingStore();
const displayPlans = ref(fallbackPlans);
const loading = ref(true);

const FEATURE_LABELS = {
    ai_chat: 'AI chat',
    knowledge_base: 'Knowledge base',
    ticket_system: 'Ticket system',
    analytics: 'Analytics',
    integrations: 'Integrations',
    custom_branding: 'Custom branding',
    priority_support: 'Priority support',
    sso: 'SSO',
    audit_logs: 'Audit logs'
};

function formatPlan(p) {
    const isFree = !!p.is_free || Number(p.price_monthly) === 0;
    const cta = isFree ? 'Get started' : p.name === 'Enterprise' ? 'Contact sales' : 'Start free trial';
    const items = [];
    if (p.features && typeof p.features === 'object') {
        for (const [key, enabled] of Object.entries(p.features)) {
            if (enabled === true) items.push(FEATURE_LABELS[key] || key.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase()));
        }
    }
    if (p.limits) {
        if (p.limits.conversations != null) items.push(`Up to ${Number(p.limits.conversations).toLocaleString()} conversations`);
        if (p.limits.agents != null) items.push(Number(p.limits.agents) >= 999999 ? 'Unlimited team members' : `Up to ${Number(p.limits.agents).toLocaleString()} team member${p.limits.agents > 1 ? 's' : ''}`);
    }
    return {
        id: p.id,
        name: p.name,
        blurb: p.description || '',
        price: isFree ? 'Free' : p.formatted_price_monthly,
        per: isFree ? '' : '/month',
        featured: !!p.badge,
        badge: p.badge || null,
        cta,
        items: items.length ? items : ['Contact us for details']
    };
}

const gridClass = computed(() => (displayPlans.value.length > 3 ? 'grid items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4' : 'grid items-stretch gap-6 md:grid-cols-3'));

onMounted(async () => {
    try {
        const list = await billingStore.fetchPlans({ public: '1' });
        if (Array.isArray(list) && list.length) {
            displayPlans.value = list.map(formatPlan);
        }
    } catch {
        // keep the static fallback
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <SectionShell id="pricing" labelledby="pricing-title">
        <div class="flex flex-col gap-10 md:gap-12" v-reveal>
            <SectionHeading center :eyebrow="eyebrow" :title="title" :lead="lead" title-id="pricing-title" :as="headingTag" />
            <div v-if="loading" class="grid items-stretch gap-6 md:grid-cols-3">
                <div v-for="i in 3" :key="i" class="h-80 animate-pulse rounded-[22px] bg-card" />
            </div>
            <div v-else :class="gridClass">
                <PricingCard v-for="p in displayPlans" :key="p.id" :plan="p" />
            </div>
        </div>
    </SectionShell>
</template>
