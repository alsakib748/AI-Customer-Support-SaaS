<script setup>
import SectionShell from '@/components/common/SectionShell.vue'
import IconBox from '@/components/common/IconBox.vue'
import { capabilities } from '@/data/marketing'
</script>

<template>
  <SectionShell tint compact labelledby="trust-title">
    <div class="flex flex-col items-center gap-6">
      <h2 id="trust-title" class="text-center text-base font-semibold">
        Built for teams that care about fast, intelligent customer support
      </h2>
      <ul class="grid w-full max-w-4xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="c in capabilities" :key="c.label" class="flex items-center gap-3 text-[15px] font-semibold text-ink">
          <IconBox :icon="c.icon" small />{{ c.label }}
        </li>
      </ul>
    </div>
  </SectionShell>
</template>
