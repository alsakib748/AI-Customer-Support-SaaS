<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import FeatureCard from '@/components/common/FeatureCard.vue'
import { problems } from '@/data/marketing'
</script>

<template>
  <SectionShell id="solutions" labelledby="problem-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        eyebrow="The problem"
        title="Customer support is getting harder."
        title-id="problem-title"
        lead="Questions arrive from every direction, the same answers get typed again and again, and the knowledge your team needs lives in too many places."
      />
      <div class="grid gap-5 md:grid-cols-3">
        <FeatureCard v-for="p in problems" :key="p.title" :icon="p.icon" :title="p.title" :body="p.body" danger :hover="false" />
      </div>
      <p class="text-xl font-semibold tracking-tight text-ink md:text-2xl">There is a better way.</p>
    </div>
  </SectionShell>
</template>
