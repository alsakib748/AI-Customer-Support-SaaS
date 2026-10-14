<script setup>
import { useSeo } from '@/composables/useSeo'
import PricingSection from '@/components/marketing/PricingSection.vue'
import FAQSection from '@/components/marketing/FAQSection.vue'
import FinalCTA from '@/components/marketing/FinalCTA.vue'

useSeo({
  title: 'Pricing',
  description: 'Simple pricing for AI-powered customer support. Compare plans and start your free trial.',
  path: '/pricing',
})
</script>

<template>
  <div class="bg-gradient-to-b from-alt to-page">
    <PricingSection
      eyebrow="Pricing"
      title="Simple pricing for AI-powered customer support."
      lead="Choose the plan that fits your support team. Start free, upgrade when your volume grows."
      heading-tag="h1"
    />
  </div>
  <FAQSection />
  <FinalCTA />
</template>
