<script setup>
import { Check } from 'lucide-vue-next';
import BaseButton from './BaseButton.vue';
import { AUTH } from '@/data/marketing';

defineProps({
    plan: { type: Object, required: true }
});
</script>

<template>
    <div class="relative flex flex-col gap-5 rounded-[22px] bg-card p-6 md:p-8" :class="plan.featured ? 'border-2 border-brand shadow-soft' : 'border border-line'">
        <span v-if="plan.featured" class="absolute -top-3.5 right-6 rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">
            {{ plan.badge || 'Most popular' }}
        </span>
        <div class="flex flex-col gap-1.5">
            <h3 class="text-xl font-semibold tracking-tight">{{ plan.name }}</h3>
            <p class="text-sm text-muted">{{ plan.blurb }}</p>
        </div>
        <p class="text-[40px] leading-none font-bold tracking-tight text-ink md:text-5xl">
            {{ plan.price }}<span v-if="plan.per" class="ml-1 text-base font-medium tracking-normal text-muted">{{ plan.per }}</span>
        </p>
        <ul class="flex flex-col gap-3 text-[15px] text-body">
            <li v-for="item in plan.items" :key="item" class="flex items-start gap-2.5">
                <Check class="mt-0.5 size-[18px] shrink-0 text-accent" aria-hidden="true" />
                {{ item }}
            </li>
        </ul>
        <BaseButton class="mt-auto" :variant="plan.featured ? 'primary' : 'secondary'" :to="plan.id === 'enterprise' ? '/contact' : AUTH.registerWithPlan(plan.id)" block>
            {{ plan.cta }}
        </BaseButton>
    </div>
</template>
