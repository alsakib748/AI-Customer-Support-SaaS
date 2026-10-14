<script setup>
defineProps({
  tone: { type: String, default: 'gray' }, // ok | warn | ai | blue | gray
})

const tones = {
  ok: 'bg-ok-bg text-ok-fg',
  warn: 'bg-warn-bg text-warn-fg',
  ai: 'bg-ai-soft text-ai',
  blue: 'bg-blue-bg text-blue-fg',
  gray: 'bg-gray-bg text-gray-fg',
}
</script>

<template>
  <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="tones[tone]">
    <slot />
  </span>
</template>
