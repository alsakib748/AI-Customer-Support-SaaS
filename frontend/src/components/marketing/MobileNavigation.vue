<script setup>
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/common/BaseButton.vue'
import ThemeSwitcher from './ThemeSwitcher.vue'
import { navLinks, resourceLinks, AUTH } from '@/data/marketing'

defineProps({ id: { type: String, default: 'mobile-menu' } })
const emit = defineEmits(['close'])
</script>

<template>
  <div :id="id" class="border-t border-line bg-page lg:hidden">
    <div class="mx-auto flex max-h-[calc(100vh-4.5rem)] max-w-7xl flex-col gap-1 overflow-y-auto px-4 pt-2 pb-6 sm:px-6">
      <nav aria-label="Mobile" class="flex flex-col">
        <RouterLink
          v-for="link in [...navLinks, ...resourceLinks]"
          :key="link.label"
          :to="link.to"
          class="border-b border-line py-4 text-[17px] font-medium text-ink"
          @click="emit('close')"
        >
          {{ link.label }}
        </RouterLink>
      </nav>
      <div class="mt-5 flex flex-col gap-3">
        <BaseButton variant="secondary" :to="AUTH.login" block @click="emit('close')">Sign in</BaseButton>
        <BaseButton variant="primary" :to="AUTH.register" block @click="emit('close')">Get started free</BaseButton>
      </div>
      <div class="mt-6"><ThemeSwitcher inline /></div>
    </div>
  </div>
</template>
