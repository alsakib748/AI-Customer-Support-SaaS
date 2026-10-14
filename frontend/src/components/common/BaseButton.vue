<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | outline | ghost | white | ghostDark
  size: { type: String, default: 'md' }, // sm | md | lg
  to: { type: [String, Object], default: null }, // internal route
  href: { type: String, default: null }, // external link
  type: { type: String, default: 'button' },
  block: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const tag = computed(() => (props.to ? RouterLink : props.href ? 'a' : 'button'))

const base =
  'inline-flex items-center justify-center gap-2 rounded-xl font-semibold whitespace-nowrap transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-400 disabled:cursor-not-allowed disabled:border-line disabled:bg-alt disabled:text-muted'

const variants = {
  primary: 'bg-brand text-white hover:bg-brand-hover',
  secondary: 'border border-line bg-card text-ink hover:bg-soft',
  outline: 'border border-brand text-accent hover:bg-soft',
  ghost: 'text-body hover:bg-alt hover:text-ink',
  white: 'bg-white text-slate-900 hover:bg-blue-50',
  ghostDark: 'border border-slate-600 text-white hover:bg-white/10',
}

const sizes = {
  sm: 'min-h-10 px-4 text-sm',
  md: 'min-h-12 px-5 text-base',
  lg: 'min-h-[52px] px-6 text-base',
}

const classes = computed(() => [base, variants[props.variant], sizes[props.size], props.block && 'w-full'])
</script>

<template>
  <component
    :is="tag"
    :to="to || undefined"
    :href="href || undefined"
    :type="tag === 'button' ? type : undefined"
    :disabled="tag === 'button' ? disabled : undefined"
    :class="classes"
  >
    <slot />
  </component>
</template>
