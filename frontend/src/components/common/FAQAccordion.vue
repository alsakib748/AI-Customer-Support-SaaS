<script setup>
import { ref } from 'vue'
import { Plus, Minus } from 'lucide-vue-next'

defineProps({
  items: { type: Array, required: true }, // [{ q, a }]
})

const openIndex = ref(0)
const uid = Math.random().toString(36).slice(2, 8)

function toggle(i) {
  openIndex.value = openIndex.value === i ? -1 : i
}
</script>

<template>
  <div class="border-t border-line">
    <div v-for="(item, i) in items" :key="item.q" class="border-b border-line">
      <h3>
        <button
          type="button"
          class="flex w-full items-center justify-between gap-4 px-1 py-5 text-left text-[17px] leading-snug font-semibold text-ink transition-colors hover:text-accent"
          :aria-expanded="openIndex === i"
          :aria-controls="`faq-${uid}-${i}`"
          @click="toggle(i)"
        >
          <span>{{ item.q }}</span>
          <component :is="openIndex === i ? Minus : Plus" class="size-5 shrink-0 text-accent" aria-hidden="true" />
        </button>
      </h3>
      <div
        :id="`faq-${uid}-${i}`"
        role="region"
        class="grid transition-[grid-template-rows] duration-200 ease-out"
        :class="openIndex === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
      >
        <div class="overflow-hidden">
          <p class="max-w-2xl px-1 pb-5 text-body">{{ item.a }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
