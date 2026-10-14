<script setup>
import { RouterLink } from 'vue-router';
import { footerColumns } from '@/data/marketing';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-line bg-footer pt-14 pb-9 text-[15px] text-slate-400">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-[1.6fr_repeat(4,1fr)]">
                <div class="col-span-2 flex max-w-xs flex-col gap-3.5 md:col-span-4 lg:col-span-1">
                    <RouterLink to="/" class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-white">
                        <img src="/SupportAI-brand-assets/svg/logo-horizontal-mono-white.svg" alt="SupportAI" class="h-8 w-auto" />
                    </RouterLink>
                    <p>AI-powered customer support for modern businesses.</p>
                </div>

                <nav v-for="col in footerColumns" :key="col.title" class="flex flex-col gap-2.5" :aria-label="col.title">
                    <h2 class="mb-1 text-sm font-semibold text-white">{{ col.title }}</h2>
                    <RouterLink v-for="l in col.links" :key="l.label" :to="l.to" class="text-slate-300 transition-colors hover:text-white">
                        {{ l.label }}
                    </RouterLink>
                </nav>
            </div>

            <div class="mt-12 flex flex-wrap justify-between gap-3 border-t border-white/10 pt-6 text-sm">
                <span>© {{ year }} SupportAI. All rights reserved.</span>
                <span>Built for teams that care about customer experience.</span>
            </div>
        </div>
    </footer>
</template>
