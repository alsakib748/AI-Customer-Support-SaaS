<script setup>
import { Sparkles, BookOpen, Check } from 'lucide-vue-next'
import StatusPill from '@/components/common/StatusPill.vue'
import IconBox from '@/components/common/IconBox.vue'

const list = [
  { name: 'Priya Nair', text: 'How do I reset my password?', tone: 'ai', tag: 'AI', on: true },
  { name: 'Tom Becker', text: 'I was charged twice this month', tone: 'warn', tag: 'With agent' },
  { name: 'Lena Ortiz', text: 'Can I add a teammate?', tone: 'ok', tag: 'Resolved' },
  { name: 'Sam Okoye', text: 'Where is the API key page?', tone: 'ai', tag: 'AI' },
]
</script>

<template>
  <div
    class="overflow-hidden rounded-3xl border border-line bg-card shadow-soft"
    role="img"
    aria-label="Product preview: an inbox with an AI-resolved conversation and a conversation handed to a human agent"
  >
    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
      <div class="flex min-w-0 items-center gap-2.5">
        <IconBox :icon="Sparkles" ai small />
        <div class="min-w-0 leading-tight">
          <p class="text-sm font-semibold text-ink">Conversations</p>
          <p class="text-xs text-muted">Demo workspace</p>
        </div>
      </div>
      <StatusPill tone="ok">AI resolved</StatusPill>
    </div>

    <div class="grid md:grid-cols-[200px_minmax(0,1fr)]">
      <div class="hidden flex-col gap-1.5 border-r border-line bg-alt p-3 md:flex" aria-hidden="true">
        <div
          v-for="c in list"
          :key="c.name"
          class="flex flex-col gap-0.5 rounded-xl p-2.5 text-[13px] leading-snug"
          :class="c.on && 'border border-line bg-card'"
        >
          <span class="font-semibold text-ink">{{ c.name }}</span>
          <span class="truncate text-muted">{{ c.text }}</span>
          <StatusPill :tone="c.tone" class="mt-1 self-start">{{ c.tag }}</StatusPill>
        </div>
      </div>

      <div class="flex min-w-0 flex-col gap-3.5 p-5">
        <p class="max-w-[88%] self-end rounded-2xl rounded-br-md bg-brand px-4 py-2.5 text-sm text-white">How do I reset my password?</p>
        <div class="max-w-[92%] self-start rounded-2xl rounded-bl-md border border-ai-line bg-ai-soft px-4 py-3 text-sm text-ink">
          <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-ai">
            <Sparkles class="size-3.5" aria-hidden="true" />AI assistant
          </p>
          Open Settings, choose Security, then select Reset password. We will email you a link that stays valid for one hour.
          <span class="mt-2 flex w-fit items-center gap-1.5 rounded-lg border border-line bg-card px-2 py-0.5 text-xs text-body">
            <BookOpen class="size-3.5" aria-hidden="true" />Account help · Resetting your password
          </span>
        </div>
        <p class="max-w-[88%] self-end rounded-2xl rounded-br-md bg-brand px-4 py-2.5 text-sm text-white">Thanks, that worked.</p>
        <p class="flex items-center gap-2 text-[13px] font-semibold text-ok-fg">
          <Check class="size-4" aria-hidden="true" />Resolved by AI
        </p>
        <div class="flex items-center gap-2.5 border-t border-dashed border-line pt-3.5 text-[13px] text-body">
          <StatusPill tone="warn">Handoff</StatusPill>Billing question assigned to a human agent
        </div>
      </div>
    </div>
  </div>
</template>
