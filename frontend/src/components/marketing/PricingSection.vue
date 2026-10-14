<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import PricingCard from '@/components/common/PricingCard.vue'
import { plans } from '@/data/marketing'

defineProps({
  // On the dedicated /pricing page the page heading is the H1, so this one is an H2.
  eyebrow: { type: String, default: 'Simple, transparent pricing' },
  title: { type: String, default: 'A plan for every stage of growth.' },
  lead: { type: String, default: 'Start free, upgrade when your support volume grows.' },
  headingTag: { type: String, default: 'h2' },
})
</script>

<template>
  <SectionShell id="pricing" labelledby="pricing-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading center :eyebrow="eyebrow" :title="title" :lead="lead" title-id="pricing-title" :as="headingTag" />
      <div class="grid items-stretch gap-6 md:grid-cols-3">
        <PricingCard v-for="p in plans" :key="p.id" :plan="p" />
      </div>
    </div>
  </SectionShell>
</template>
