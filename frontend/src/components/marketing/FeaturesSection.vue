<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import FeatureCard from '@/components/common/FeatureCard.vue'
import { features } from '@/data/marketing'
</script>

<template>
  <SectionShell id="features" tint labelledby="features-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        center
        eyebrow="Everything in one workspace"
        title="Everything your support team needs."
        title-id="features-title"
        lead="AI-assisted replies, shared conversations, tickets, a knowledge base, team permissions and reporting, working together instead of in six tools."
      />
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <FeatureCard v-for="f in features" :key="f.id" :icon="f.icon" :title="f.title" :body="f.body" :link="f.link" :to="f.to" />
      </div>
    </div>
  </SectionShell>
</template>
