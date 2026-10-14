<script setup>
defineProps({
  icon: { type: [Object, Function], required: true },
  ai: { type: Boolean, default: false },
  danger: { type: Boolean, default: false },
  small: { type: Boolean, default: false },
})
</script>

<template>
  <span
    class="flex shrink-0 items-center justify-center rounded-xl"
    :class="[small ? 'size-9' : 'size-11', danger ? 'bg-danger-bg text-danger-fg' : ai ? 'bg-ai-soft text-ai' : 'bg-soft text-accent']"
  >
    <component :is="icon" :class="small ? 'size-[18px]' : 'size-[22px]'" :stroke-width="1.75" aria-hidden="true" />
  </span>
</template>
