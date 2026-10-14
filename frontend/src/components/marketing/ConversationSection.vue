<script setup>
import { ArrowRight, Check, StickyNote } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import StatusPill from '@/components/common/StatusPill.vue'
import { inboxRows } from '@/data/marketing'

const tabs = ['All', 'Open', 'Assigned to me', 'Resolved']
const points = [
  'Assign and prioritize conversations',
  'Track tickets from open to resolved',
  'Collaborate with notes your customers never see',
]
</script>

<template>
  <SectionShell id="conversations" tint labelledby="conv-title">
    <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16" v-reveal>
      <div
        class="order-2 overflow-hidden rounded-[22px] border border-line bg-card shadow-soft lg:order-1"
        role="img"
        aria-label="Product preview: a shared inbox with filters, assigned conversations and an internal note"
      >
        <div class="flex flex-wrap gap-1.5 border-b border-line p-4">
          <span
            v-for="(t, i) in tabs"
            :key="t"
            class="rounded-full px-3.5 py-1.5 text-[13px] font-semibold"
            :class="i === 0 ? 'bg-ink text-page' : 'bg-gray-bg text-gray-fg'"
          >
            {{ t }}
          </span>
        </div>
        <ul>
          <li v-for="r in inboxRows" :key="r.name" class="flex items-center gap-3 border-b border-line px-4 py-3.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold text-white" :style="{ background: r.color }">
              {{ r.initials }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-ink">{{ r.name }}</span>
              <span class="block truncate text-[13px] text-muted">{{ r.preview }}</span>
            </span>
            <StatusPill :tone="r.tone">{{ r.status }}</StatusPill>
          </li>
        </ul>
        <div class="m-4 flex items-start gap-2.5 rounded-xl border border-note-line bg-note-bg p-3 text-[13px] text-note-fg">
          <StickyNote class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <p><strong>Internal note</strong><br />Customer was refunded for the duplicate charge last week. Confirm before replying.</p>
        </div>
      </div>

      <div class="order-1 flex min-w-0 flex-col gap-5 lg:order-2">
        <SectionHeading
          eyebrow="Conversations and tickets"
          title="One workspace for every conversation."
          title-id="conv-title"
          lead="Give your team a shared inbox for customer conversations. Assign them, turn them into tickets, leave internal notes and keep the full history in one place."
        />
        <ul class="flex flex-col gap-2.5">
          <li v-for="p in points" :key="p" class="flex items-center gap-2.5 text-body">
            <Check class="size-[18px] shrink-0 text-accent" aria-hidden="true" />{{ p }}
          </li>
        </ul>
        <RouterLink to="/features#conversations" class="inline-flex items-center gap-1.5 font-semibold text-accent hover:underline">
          Explore the agent workspace <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>
    </div>
  </SectionShell>
</template>
