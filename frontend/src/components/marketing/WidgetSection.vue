<script setup>
import { ref } from 'vue'
import { Copy, Check, Sparkles } from 'lucide-vue-next'
import SectionShell from '@/components/common/SectionShell.vue'
import SectionHeading from '@/components/common/SectionHeading.vue'

// Placeholder values only. Never put a real key or workspace id in marketing copy.
const snippet = '<script src="https://cdn.your-domain.com/widget.js" data-workspace="YOUR_WORKSPACE_ID" async><\/script>'
const copied = ref(false)

async function copy() {
  try {
    await navigator.clipboard.writeText(snippet)
    copied.value = true
    setTimeout(() => (copied.value = false), 1800)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <SectionShell id="widget" labelledby="widget-title">
    <div class="grid items-center gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-16" v-reveal>
      <div class="flex min-w-0 flex-col gap-5">
        <SectionHeading
          eyebrow="Chat widget"
          title="Turn any website into an AI support channel."
          title-id="widget-title"
          lead="Add one script to your site and customers can start a conversation. The AI answers first, and your agents pick up when it is needed."
        />
        <div class="overflow-hidden rounded-2xl bg-code">
          <div class="flex items-center justify-between border-b border-white/10 px-4 py-2 text-xs text-slate-400">
            <span class="font-mono">index.html</span>
            <button type="button" class="flex items-center gap-1.5 rounded-md px-2 py-1 text-slate-300 transition-colors hover:bg-white/10" @click="copy">
              <component :is="copied ? Check : Copy" class="size-3.5" aria-hidden="true" />
              {{ copied ? 'Copied' : 'Copy' }}
            </button>
          </div>
          <pre class="overflow-x-auto px-5 py-4 font-mono text-[13px] leading-relaxed text-slate-200"><code>{{ snippet }}</code></pre>
        </div>
      </div>

      <div class="overflow-hidden rounded-[20px] border border-line bg-card shadow-soft" role="img" aria-label="A website with the SupportAI chat widget open">
        <div class="flex items-center gap-1.5 border-b border-line bg-alt px-4 py-3">
          <span class="size-2.5 rounded-full bg-line"></span><span class="size-2.5 rounded-full bg-line"></span><span class="size-2.5 rounded-full bg-line"></span>
          <span class="ml-2.5 font-mono text-xs text-muted">your-website.com</span>
        </div>
        <div class="relative h-[300px] bg-gradient-to-b from-card to-alt p-6">
          <div class="mb-3 h-3 w-[46%] rounded-md bg-line"></div>
          <div class="mb-3 h-3 w-[72%] rounded-md bg-line"></div>
          <div class="mb-3 h-3 w-[58%] rounded-md bg-line"></div>
          <div class="h-3 w-[34%] rounded-md bg-line"></div>
          <div class="absolute right-5 bottom-5 w-[min(270px,calc(100%-40px))] overflow-hidden rounded-[18px] border border-line bg-card shadow-soft">
            <div class="flex items-center gap-2 bg-brand px-4 py-3 text-sm font-semibold text-white">
              <Sparkles class="size-4" aria-hidden="true" />Support
            </div>
            <div class="flex flex-col gap-2 p-3.5 text-[13px]">
              <p class="self-start rounded-2xl rounded-bl-md bg-card2 px-3.5 py-2 text-ink">Hi! How can we help today?</p>
              <p class="self-end rounded-2xl rounded-br-md bg-brand px-3.5 py-2 text-white">Do you offer team accounts?</p>
              <p class="self-start rounded-2xl rounded-bl-md border border-ai-line bg-ai-soft px-3.5 py-2 text-ink">
                Yes. Invite teammates from Settings and choose a role for each one.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SectionShell>
</template>
