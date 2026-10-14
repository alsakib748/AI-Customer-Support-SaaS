<script setup>
import { ArrowRight, Play, Check } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import ProductPreview from './ProductPreview.vue';
import { AUTH } from '@/data/marketing';

const ticks = ['AI answers with human handoff', 'Knowledge base', 'Chat widget for your site', 'Team roles and analytics'];
</script>

<template>
    <section class="bg-gradient-to-b from-alt to-page pt-10 pb-14 md:pt-16 md:pb-20 lg:pt-[72px] lg:pb-24" aria-labelledby="hero-title">
        <div class="mx-auto grid w-full max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.08fr] lg:gap-14 lg:px-8">
            <div class="flex min-w-0 flex-col gap-6" v-reveal>
                <p class="flex w-fit items-center gap-2 rounded-full border border-line bg-card py-1.5 pr-3.5 pl-2.5 text-sm font-semibold text-ink">
                    <span class="size-2 rounded-full bg-ai" aria-hidden="true"></span>
                    AI-powered customer support
                </p>
                <h1 id="hero-title" class="text-4xl leading-[1.08] font-bold tracking-tight sm:text-5xl lg:text-[62px]">Turn customer questions into intelligent conversations.</h1>
                <p class="max-w-xl text-base leading-relaxed text-body sm:text-lg">Automate repetitive support with AI, empower your agents, and manage every conversation, ticket and answer from one workspace.</p>
                <ul class="flex flex-wrap gap-x-6 gap-y-2.5 text-[15px] text-body">
                    <li v-for="t in ticks" :key="t" class="flex items-center gap-2"><Check class="size-[18px] text-accent" aria-hidden="true" />{{ t }}</li>
                </ul>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <BaseButton :to="AUTH.register" size="lg"> Start free trial <ArrowRight class="size-4" aria-hidden="true" /> </BaseButton>
                    <BaseButton variant="secondary" size="lg" to="/#how-it-works"> <Play class="size-4" aria-hidden="true" /> Watch demo </BaseButton>
                </div>
                <p class="text-sm text-muted">No credit card required · Set up in minutes</p>
            </div>

            <div class="min-w-0" v-reveal>
                <ProductPreview />
            </div>
        </div>
    </section>
</template>
