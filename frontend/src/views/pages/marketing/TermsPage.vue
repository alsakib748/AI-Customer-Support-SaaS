<script setup>
import { useSeo } from '@/composables/useSeo'
import LegalPage from './LegalPage.vue'

useSeo({ title: 'Terms of Service', description: 'The terms that apply when you use SupportAI.', path: '/terms' })

const sections = [
  { heading: 'Using the service', body: '[Describe who may use SupportAI and the account responsibilities.]' },
  { heading: 'Acceptable use', body: '[Describe what customers may and may not do with the service.]' },
  { heading: 'Plans, billing and cancellation', body: '[Match this to your real billing: trial terms, renewal, refunds and how to cancel.]' },
  { heading: 'Customer content and data', body: '[Explain who owns content and how it is handled.]' },
  { heading: 'Availability and support', body: '[State only the commitments you can keep.]' },
  { heading: 'Liability and changes', body: '[Add limitation of liability, governing law and how terms change.]' },
]
</script>

<template>
  <LegalPage title="Terms of Service" :sections="sections" />
</template>
