<script setup>
import { ArrowRight } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import { kbSources } from '@/data/marketing'
</script>

<template>
  <SectionShell id="knowledge-base" tint labelledby="kb-title">
    <div class="grid items-center gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-16" v-reveal>
      <div class="flex min-w-0 flex-col gap-5">
        <SectionHeading
          eyebrow="Knowledge base"
          title="Teach your AI how your business works."
          title-id="kb-title"
          lead="Your AI should not guess. Give it your products, policies and help content so customers get consistent answers, and so your team can see where an answer came from."
        />
        <RouterLink to="/features#knowledge-base" class="inline-flex items-center gap-1.5 font-semibold text-accent hover:underline">
          Explore the knowledge base <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 items-center gap-3 sm:grid-cols-[1fr_32px_1fr]" role="group" aria-label="Knowledge sources feed the AI, which answers the customer">
        <ul class="flex flex-col gap-2.5">
          <li v-for="k in kbSources" :key="k.label" class="flex items-center gap-3 rounded-xl border border-line bg-card px-4 py-3 text-[15px] font-medium text-ink">
            <component :is="k.icon" class="size-4 text-accent" aria-hidden="true" />{{ k.label }}
          </li>
        </ul>
        <ArrowRight class="mx-auto size-5 rotate-90 text-muted sm:rotate-0" aria-hidden="true" />
        <div class="flex flex-col gap-3">
          <div class="rounded-2xl border border-ai-line bg-ai-soft p-4">
            <p class="text-xs font-semibold tracking-wider text-ai uppercase">AI assistant</p>
            <p class="mt-1 text-[15px] font-semibold text-ink">Reads your content before it replies</p>
          </div>
          <div class="rounded-2xl border border-line bg-card p-4">
            <p class="text-xs font-semibold tracking-wider text-muted uppercase">Customer answer</p>
            <p class="mt-1 text-[15px] font-semibold text-ink">Consistent, with the source shown to your team</p>
          </div>
        </div>
      </div>
    </div>
  </SectionShell>
</template>
