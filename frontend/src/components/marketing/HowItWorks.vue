<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import StatusPill from '@/components/common/StatusPill.vue'
import { steps } from '@/data/marketing'
</script>

<template>
  <SectionShell id="how-it-works" tint labelledby="how-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        eyebrow="How it works"
        title="Connect your knowledge. Assist your customers. Escalate when needed."
        title-id="how-title"
      />
      <ol class="grid gap-5 md:grid-cols-3">
        <li v-for="(s, i) in steps" :key="s.title" class="flex flex-col gap-3 rounded-card border border-line bg-card p-6 md:p-7">
          <StatusPill tone="blue" class="self-start">Step {{ i + 1 }}</StatusPill>
          <h3 class="text-xl leading-snug font-semibold tracking-tight">{{ s.title }}</h3>
          <p class="text-body">{{ s.body }}</p>
        </li>
      </ol>
    </div>
  </SectionShell>
</template>
