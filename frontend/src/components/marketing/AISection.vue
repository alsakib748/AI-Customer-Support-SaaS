<script setup>
import { ArrowRight, Sparkles } from 'lucide-vue-next'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import StatusPill from '@/components/common/StatusPill.vue'
</script>

<template>
  <SectionShell id="ai" labelledby="ai-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        ai
        eyebrow="Meet your AI teammate"
        title="Your support team, with an AI teammate."
        title-id="ai-title"
        lead="Resolve repetitive questions automatically while your human agents stay in control of everything that needs judgment."
      >
        <template #eyebrow-icon><Sparkles class="size-4" aria-hidden="true" /></template>
      </SectionHeading>

      <ol class="flex flex-col gap-3 lg:flex-row lg:items-stretch" aria-label="How a customer question is handled">
        <template v-for="(n, i) in [
          { small: 'Customer', title: 'Asks a question in the chat widget' },
          { small: 'AI', title: 'Understands the question', ai: true },
          { small: 'Knowledge base', title: 'Finds your approved answers' },
          { small: 'AI', title: 'Writes the reply', ai: true },
        ]" :key="n.title">
          <li
            class="flex flex-1 flex-col gap-2 rounded-2xl border p-4"
            :class="n.ai ? 'border-ai-line bg-ai-soft' : 'border-line bg-card'"
          >
            <span class="text-xs font-semibold tracking-wider uppercase" :class="n.ai ? 'text-ai' : 'text-muted'">{{ n.small }}</span>
            <span class="text-[15px] leading-snug font-semibold text-ink">{{ n.title }}</span>
          </li>
          <li class="flex items-center justify-center text-muted" aria-hidden="true">
            <ArrowRight class="size-5 rotate-90 lg:rotate-0" />
          </li>
        </template>
        <li class="flex flex-[1.3] flex-col gap-2.5 rounded-2xl border border-line bg-card p-4">
          <span class="text-xs font-semibold tracking-wider text-muted uppercase">Resolved?</span>
          <span class="flex items-center gap-2 text-sm text-ink"><StatusPill tone="ok">Yes</StatusPill>Conversation closes</span>
          <span class="flex items-center gap-2 text-sm text-ink"><StatusPill tone="warn">No</StatusPill>Handed to an agent</span>
        </li>
      </ol>
    </div>
  </SectionShell>
</template>
