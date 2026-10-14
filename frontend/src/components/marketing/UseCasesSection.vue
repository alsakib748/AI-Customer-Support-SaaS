<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import FeatureCard from '@/components/common/FeatureCard.vue'
import { useCases } from '@/data/marketing'
</script>

<template>
  <SectionShell tint labelledby="usecases-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading eyebrow="Use cases" title="Built for modern support teams." title-id="usecases-title" />
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <FeatureCard v-for="u in useCases" :key="u.title" :icon="u.icon" :title="u.title" :body="u.body" />
      </div>
    </div>
  </SectionShell>
</template>
