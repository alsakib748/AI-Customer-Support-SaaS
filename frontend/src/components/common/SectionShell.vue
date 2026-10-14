<script setup>
defineProps({
  id: { type: String, default: undefined },
  labelledby: { type: String, default: undefined },
  tint: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <section
    :id="id"
    :aria-labelledby="labelledby"
    :class="[compact ? 'py-10 md:py-12' : 'py-14 md:py-20 lg:py-24', tint && 'border-y border-line bg-alt']"
  >
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <slot />
    </div>
  </section>
</template>
