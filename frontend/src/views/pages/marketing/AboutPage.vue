<script setup>
import { useSeo } from '@/composables/useSeo'
import SectionShell from '@/components/common/SectionShell.vue'
import FinalCTA from '@/components/marketing/FinalCTA.vue'

useSeo({
  title: 'About',
  description: 'Why we are building SupportAI: AI that handles repetitive support while your team handles what matters.',
  path: '/about',
})

const principles = [
  { title: 'AI handles the repetitive', body: 'Common questions get answered right away from your own knowledge.' },
  { title: 'People handle the important', body: 'Anything that needs judgment goes to an agent with the full history.' },
  { title: 'Claims you can check', body: 'We describe what the product does today and nothing more.' },
]
</script>

<template>
  <section class="bg-gradient-to-b from-alt to-page py-14 md:py-20" aria-labelledby="about-h1">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex max-w-3xl flex-col gap-4">
        <p class="text-[13px] font-semibold tracking-wide text-accent uppercase">About</p>
        <h1 id="about-h1" class="text-4xl leading-[1.1] font-bold tracking-tight md:text-5xl">
          Support software that respects your team and your customers.
        </h1>
        <!-- Replace with your real company story. -->
        <p class="max-w-2xl text-base leading-relaxed text-body md:text-lg">
          [YOUR STORY: who you are, why you built SupportAI, and who it is for. Two or three honest sentences work best.]
        </p>
      </div>
    </div>
  </section>

  <SectionShell labelledby="principles-title">
    <h2 id="principles-title" class="mb-8 text-3xl font-bold tracking-tight md:text-4xl">What we believe</h2>
    <div class="grid gap-5 md:grid-cols-3">
      <div v-for="p in principles" :key="p.title" class="rounded-card border border-line bg-card p-6 md:p-7">
        <h3 class="mb-2 text-xl font-semibold tracking-tight">{{ p.title }}</h3>
        <p class="text-body">{{ p.body }}</p>
      </div>
    </div>
  </SectionShell>

  <FinalCTA />
</template>
