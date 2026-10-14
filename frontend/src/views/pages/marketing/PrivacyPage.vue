<script setup>
import { useSeo } from '@/composables/useSeo'
import LegalPage from './LegalPage.vue'

useSeo({ title: 'Privacy Policy', description: 'How SupportAI collects, uses and protects personal data.', path: '/privacy' })

const sections = [
  { heading: 'Information we collect', body: '[Describe the account, usage and conversation data you collect and why.]' },
  { heading: 'How we use information', body: '[Describe how you use data to provide, secure and improve the service.]' },
  { heading: 'AI processing and your knowledge content', body: '[Describe how conversation and knowledge content is processed by AI providers and what is retained.]' },
  { heading: 'Sharing and subprocessors', body: '[List the categories of providers you share data with.]' },
  { heading: 'Retention and deletion', body: '[State how long you keep data and how customers can request deletion.]' },
  { heading: 'Your rights and contact', body: '[Explain access, correction and deletion rights and give a contact address.]' },
]
</script>

<template>
  <LegalPage title="Privacy Policy" :sections="sections" />
</template>
