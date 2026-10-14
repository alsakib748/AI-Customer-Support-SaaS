<script setup>
import { Info } from 'lucide-vue-next'

defineProps({
  title: { type: String, required: true },
  sections: { type: Array, required: true }, // [{ heading, body }]
})
</script>

<template>
  <section class="py-14 md:py-20" aria-labelledby="legal-h1">
    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8">
      <h1 id="legal-h1" class="text-4xl leading-[1.1] font-bold tracking-tight md:text-5xl">{{ title }}</h1>
      <p class="mt-3 text-sm text-muted">Last updated: [DATE]</p>

      <div class="mt-6 flex items-start gap-3 rounded-xl border border-note-line bg-note-bg p-4 text-sm text-note-fg">
        <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <p>This is placeholder structure, not a legal document. Replace each section with text reviewed by your legal advisor before launch.</p>
      </div>

      <div class="mt-10 flex flex-col gap-8">
        <article v-for="s in sections" :key="s.heading">
          <h2 class="mb-2 text-xl font-semibold tracking-tight">{{ s.heading }}</h2>
          <p class="text-body">{{ s.body }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
