<script setup>
import { RouterLink } from 'vue-router'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import FAQAccordion from '@/components/common/FAQAccordion.vue'
import { faqs } from '@/data/marketing'
</script>

<template>
  <SectionShell id="faq" tint labelledby="faq-title">
    <div class="grid items-start gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-16" v-reveal>
      <div class="flex flex-col gap-4">
        <SectionHeading eyebrow="FAQ" title="Frequently asked questions" title-id="faq-title" />
        <p class="text-body">
          Can't find what you need?
          <RouterLink to="/contact" class="font-semibold text-accent hover:underline">Talk to our team.</RouterLink>
        </p>
      </div>
      <FAQAccordion :items="faqs" />
    </div>
  </SectionShell>
</template>
