<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'
import StatusPill from '@/components/common/StatusPill.vue'
import { analyticsTiles } from '@/data/marketing'

// Illustrative series only; the chart is labelled "sample data" on the page.
const weeks = ['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8']
const all = [60, 72, 84, 104, 114, 136, 148, 164]
const ai = [18, 28, 40, 54, 68, 84, 96, 112]

const X0 = 60
const X1 = 780
const Y0 = 20
const Y1 = 200
const MAX = 200
const xAt = (i) => X0 + ((X1 - X0) * i) / (weeks.length - 1)
const yAt = (v) => Y1 - ((Y1 - Y0) * v) / MAX
const toPoints = (arr) => arr.map((v, i) => `${xAt(i)},${yAt(v)}`).join(' ')

const allPoints = toPoints(all)
const aiPoints = toPoints(ai)
const area = `${X0},${Y1} ${allPoints} ${X1},${Y1}`
const ticks = [0, 50, 100, 150, 200]
</script>

<template>
  <SectionShell labelledby="analytics-title">
    <div class="flex flex-col gap-8 md:gap-10" v-reveal>
      <SectionHeading
        eyebrow="Analytics"
        title="Know what is happening inside your support operation."
        title-id="analytics-title"
        lead="Follow conversation volume, how much the AI resolves, response times and ticket progress from one dashboard."
      />

      <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="t in analyticsTiles" :key="t.label" class="flex flex-col gap-1.5 rounded-2xl border border-line bg-card p-5">
          <span class="text-sm font-medium text-muted">{{ t.label }}</span>
          <span class="text-2xl font-bold tracking-tight text-ink tabular-nums md:text-[28px]">{{ t.value }}</span>
          <StatusPill tone="gray" class="self-start">Sample data</StatusPill>
        </li>
      </ul>

      <div class="rounded-[20px] border border-line bg-card p-5 md:p-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="font-semibold text-ink">Conversation volume</p>
            <p class="text-[13px] text-muted">Last 8 weeks · sample data for illustration</p>
          </div>
          <div class="flex gap-4 text-[13px] text-body">
            <span class="flex items-center gap-1.5"><span class="h-[3px] w-3 rounded bg-accent"></span>All conversations</span>
            <span class="flex items-center gap-1.5"><span class="h-[3px] w-3 rounded bg-ai"></span>Resolved by AI</span>
          </div>
        </div>

        <svg viewBox="0 0 800 232" class="block h-auto w-full" role="img" aria-label="Line chart: conversation volume rises over eight weeks, with the AI-resolved line rising alongside it">
          <g class="stroke-line" stroke-width="1">
            <line v-for="t in ticks" :key="t" :x1="X0" :x2="X1" :y1="yAt(t)" :y2="yAt(t)" />
          </g>
          <g class="fill-muted" font-size="12" text-anchor="end">
            <text v-for="t in ticks" :key="t" :x="X0 - 10" :y="yAt(t) + 4">{{ t }}</text>
          </g>
          <g class="fill-muted" font-size="12" text-anchor="middle">
            <text v-for="(w, i) in weeks" :key="w" :x="xAt(i)" :y="222">{{ w }}</text>
          </g>
          <g class="text-accent">
            <polygon :points="area" fill="currentColor" fill-opacity="0.1" />
            <polyline :points="allPoints" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
            <circle :cx="xAt(7)" :cy="yAt(all[7])" r="5" fill="currentColor" class="stroke-card" stroke-width="2" />
          </g>
          <g class="text-ai">
            <polyline :points="aiPoints" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
            <circle :cx="xAt(7)" :cy="yAt(ai[7])" r="5" fill="currentColor" class="stroke-card" stroke-width="2" />
          </g>
        </svg>
      </div>
    </div>
  </SectionShell>
</template>
