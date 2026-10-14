<script setup>
import { RouterLink } from 'vue-router'
import { AUTH } from '@/data/marketing'
</script>

<template>
  <div class="bg-cta px-4 py-2.5 text-center text-sm text-slate-300">
    Start your free trial and set up your support workspace in minutes.
    <RouterLink :to="AUTH.register" class="font-semibold text-white underline underline-offset-4">Get started</RouterLink>
  </div>
</template>
