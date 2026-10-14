<script setup>
import { MessageSquare, Sparkles, Check, Users } from 'lucide-vue-next'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import StatusPill from '@/components/common/StatusPill.vue'

const lanes = [
  {
    tone: 'ok',
    label: 'Simple question',
    steps: [
      { icon: MessageSquare, text: 'Customer asks', kind: 'base' },
      { icon: Sparkles, text: 'AI answers from your knowledge base', kind: 'ai' },
      { icon: Check, text: 'Resolved, no agent time spent', kind: 'ok' },
    ],
  },
  {
    tone: 'warn',
    label: 'Complex question',
    steps: [
      { icon: MessageSquare, text: 'Customer asks', kind: 'base' },
      { icon: Sparkles, text: 'AI recognizes it needs a person', kind: 'ai' },
      { icon: Users, text: 'Agent takes over with the full history', kind: 'human' },
      { icon: Check, text: 'Resolved by a human', kind: 'ok' },
    ],
  },
]

const dot = {
  base: 'bg-gray-bg text-gray-fg',
  ai: 'bg-ai-soft text-ai',
  ok: 'bg-ok-bg text-ok-fg',
  human: 'bg-blue-bg text-blue-fg',
}
</script>

<template>
  <SectionShell labelledby="human-ai-title">
    <div class="flex flex-col gap-10 md:gap-12" v-reveal>
      <SectionHeading
        eyebrow="Human plus AI"
        title="AI handles the repetitive. Your team handles the important."
        title-id="human-ai-title"
      />
      <div class="grid gap-5 md:grid-cols-2">
        <div v-for="lane in lanes" :key="lane.label" class="flex flex-col gap-5 rounded-[20px] border border-line bg-card p-6 md:p-7">
          <StatusPill :tone="lane.tone" class="self-start">{{ lane.label }}</StatusPill>
          <ol class="flex flex-col">
            <li v-for="(s, i) in lane.steps" :key="s.text">
              <div class="flex items-center gap-3 text-[15px] font-medium text-ink">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="dot[s.kind]">
                  <component :is="s.icon" class="size-5" aria-hidden="true" />
                </span>
                {{ s.text }}
              </div>
              <div v-if="i < lane.steps.length - 1" class="my-1 ml-[19px] h-4 w-0.5 bg-line" aria-hidden="true"></div>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </SectionShell>
</template>
