<script setup>
import { Check } from 'lucide-vue-next'
import { useSeo } from '@/composables/useSeo'
import SectionShell from '@/components/common/SectionShell.vue'
import IconBox from '@/components/common/IconBox.vue'
import FinalCTA from '@/components/marketing/FinalCTA.vue'
import { featureDetails } from '@/data/marketing'

useSeo({
  title: 'Features',
  description:
    'AI-assisted replies, shared conversations, tickets, a knowledge base, team roles, analytics and a chat widget in one customer support workspace.',
  path: '/features',
})
</script>

<template>
  <section class="bg-gradient-to-b from-alt to-page py-14 md:py-20" aria-labelledby="features-h1">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex max-w-3xl flex-col gap-4">
        <p class="text-[13px] font-semibold tracking-wide text-accent uppercase">Features</p>
        <h1 id="features-h1" class="text-4xl leading-[1.1] font-bold tracking-tight md:text-5xl">
          Everything you need to build smarter customer support.
        </h1>
        <p class="max-w-xl text-base leading-relaxed text-body md:text-lg">
          One workspace for AI answers, conversations, tickets, knowledge, your team and your reporting.
        </p>
      </div>
    </div>
  </section>

  <SectionShell
    v-for="(f, i) in featureDetails"
    :id="f.id"
    :key="f.id"
    :tint="i % 2 === 1"
    :labelledby="`${f.id}-title`"
  >
    <div class="grid items-center gap-8 md:gap-12 lg:grid-cols-2" v-reveal>
      <div class="flex min-w-0 flex-col gap-4" :class="i % 2 === 1 && 'lg:order-2'">
        <IconBox :icon="f.icon" :ai="f.id === 'ai'" />
        <h2 :id="`${f.id}-title`" class="text-3xl leading-[1.15] font-bold tracking-tight md:text-4xl">{{ f.title }}</h2>
        <p class="max-w-xl text-base leading-relaxed text-body md:text-lg">{{ f.body }}</p>
      </div>
      <ul class="flex flex-col gap-3 rounded-[20px] border border-line bg-card p-6 md:p-8" :class="i % 2 === 1 && 'lg:order-1'">
        <li v-for="p in f.points" :key="p" class="flex items-start gap-3 text-[16px] text-ink">
          <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-soft text-accent">
            <Check class="size-3.5" aria-hidden="true" />
          </span>
          {{ p }}
        </li>
      </ul>
    </div>
  </SectionShell>

  <FinalCTA />
</template>
