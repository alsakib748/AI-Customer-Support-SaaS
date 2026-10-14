<script setup>
import { ref } from 'vue'
import { Palette, Check } from 'lucide-vue-next'
import { useTheme } from '@/composables/useTheme'
import { useDismiss } from '@/composables/useDismiss'

defineProps({
  // inline = a visible grid of swatches (used inside the mobile menu)
  inline: { type: Boolean, default: false },
})

const { preference, setTheme, themes } = useTheme()
const open = ref(false)
const root = ref(null)
useDismiss(open, root)

function choose(id) {
  setTheme(id)
  open.value = false
}
</script>

<template>
  <div v-if="inline" role="group" aria-label="Color theme">
    <p class="mb-2 text-sm font-semibold text-ink">Theme</p>
    <div class="grid grid-cols-3 gap-2">
      <button
        v-for="t in themes"
        :key="t.id"
        type="button"
        class="flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm font-medium transition-colors"
        :class="preference === t.id ? 'border-brand bg-soft text-ink' : 'border-line bg-card text-body hover:bg-alt'"
        :aria-pressed="preference === t.id"
        @click="setTheme(t.id)"
      >
        <span class="size-4 shrink-0 rounded-full border border-line" :style="{ background: t.swatch }" aria-hidden="true"></span>
        {{ t.label }}
      </button>
    </div>
  </div>

  <div v-else ref="root" class="relative">
    <button
      type="button"
      class="flex size-10 items-center justify-center rounded-xl border border-line bg-card text-ink transition-colors hover:bg-alt"
      aria-label="Change color theme"
      aria-haspopup="true"
      :aria-expanded="open"
      @click="open = !open"
    >
      <Palette class="size-[18px]" aria-hidden="true" />
    </button>
    <div
      v-if="open"
      class="absolute right-0 z-50 mt-2 w-52 rounded-2xl border border-line bg-card p-2 shadow-soft"
      role="group"
      aria-label="Color theme"
    >
      <button
        v-for="t in themes"
        :key="t.id"
        type="button"
        class="flex min-h-10 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-medium text-ink transition-colors hover:bg-alt"
        :aria-pressed="preference === t.id"
        @click="choose(t.id)"
      >
        <span class="size-5 shrink-0 rounded-full border border-line" :style="{ background: t.swatch }" aria-hidden="true"></span>
        <span class="flex-1">{{ t.label }}</span>
        <Check v-if="preference === t.id" class="size-4 text-accent" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>
