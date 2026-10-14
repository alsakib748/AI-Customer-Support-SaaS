<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  lead: { type: String, default: '' },
  titleId: { type: String, default: undefined },
  center: { type: Boolean, default: false },
  ai: { type: Boolean, default: false },
  as: { type: String, default: 'h2' },
})
</script>

<template>
  <div class="flex max-w-3xl flex-col gap-3.5" :class="center && 'mx-auto items-center text-center'">
    <p
      v-if="eyebrow"
      class="flex items-center gap-2 text-[13px] font-semibold tracking-wide uppercase"
      :class="ai ? 'text-ai' : 'text-accent'"
    >
      <slot name="eyebrow-icon" />
      {{ eyebrow }}
    </p>
    <component
      :is="as"
      :id="titleId"
      class="text-3xl leading-[1.12] font-bold tracking-tight md:text-4xl lg:text-[42px]"
    >
      {{ title }}
    </component>
    <p v-if="lead" class="max-w-xl text-base leading-relaxed text-body md:text-lg">{{ lead }}</p>
  </div>
</template>
