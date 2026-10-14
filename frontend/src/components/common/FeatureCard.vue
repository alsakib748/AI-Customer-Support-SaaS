<script setup>
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import IconBox from './IconBox.vue'

defineProps({
  icon: { type: [Object, Function], required: true },
  title: { type: String, required: true },
  body: { type: String, required: true },
  link: { type: String, default: '' },
  to: { type: [String, Object], default: null },
  danger: { type: Boolean, default: false },
  hover: { type: Boolean, default: true },
})
</script>

<template>
  <div
    class="flex flex-col gap-3.5 rounded-card border border-line bg-card p-6 transition duration-200 md:p-7"
    :class="hover && 'hover:-translate-y-0.5 hover:shadow-soft'"
  >
    <IconBox :icon="icon" :danger="danger" />
    <h3 class="text-xl leading-snug font-semibold tracking-tight">{{ title }}</h3>
    <p class="text-body">{{ body }}</p>
    <RouterLink v-if="link && to" :to="to" class="mt-auto inline-flex items-center gap-1.5 text-[15px] font-semibold text-accent hover:underline">
      {{ link }}
      <ArrowRight class="size-4" aria-hidden="true" />
    </RouterLink>
  </div>
</template>
