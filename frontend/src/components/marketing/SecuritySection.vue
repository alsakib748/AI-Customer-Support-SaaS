<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import FeatureCard from '@/components/common/FeatureCard.vue'
import { securityItems } from '@/data/marketing'
</script>

<template>
  <SectionShell id="security" labelledby="security-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        center
        eyebrow="Security and reliability"
        title="Keep workspaces, access and customer data protected."
        title-id="security-title"
        lead="Each team works in its own workspace, with access that you control."
      />
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <FeatureCard v-for="s in securityItems" :key="s.title" :icon="s.icon" :title="s.title" :body="s.body" :hover="false" />
      </div>
    </div>
  </SectionShell>
</template>
