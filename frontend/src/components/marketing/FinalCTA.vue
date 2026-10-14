<script setup>
import { ArrowRight } from 'lucide-vue-next'
import BaseButton from '@/components/common/BaseButton.vue'
import { AUTH } from '@/data/marketing'
</script>

<template>
  <section class="relative overflow-hidden border-y border-line bg-cta py-16 md:py-24" aria-labelledby="cta-title">
    <div
      class="pointer-events-none absolute -right-40 -bottom-72 size-[620px] rounded-full bg-[radial-gradient(circle,rgb(124_58_237/0.35),transparent_65%)]"
      aria-hidden="true"
    ></div>
    <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex max-w-2xl flex-col gap-6">
        <h2 id="cta-title" class="text-3xl leading-[1.12] font-bold tracking-tight text-white md:text-4xl lg:text-[42px]">
          Give your support team a smarter workspace.
        </h2>
        <p class="text-base text-slate-300 md:text-lg">
          Create your workspace, connect your knowledge, and let customers start chatting today.
        </p>
        <div class="flex flex-col gap-3 sm:flex-row">
          <BaseButton variant="white" size="lg" :to="AUTH.register">
            Start free trial <ArrowRight class="size-4" aria-hidden="true" />
          </BaseButton>
          <BaseButton variant="ghostDark" size="lg" to="/contact">Book a demo</BaseButton>
        </div>
        <p class="text-sm text-slate-400">No credit card required · Set up in minutes</p>
      </div>
    </div>
  </section>
</template>
