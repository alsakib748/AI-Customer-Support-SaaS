<script setup>
import MarketingNavbar from '@/components/marketing/MarketingNavbar.vue';
import MarketingFooter from '@/components/marketing/MarketingFooter.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col overflow-x-clip bg-page">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[60] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white"> Skip to content </a>
        <MarketingNavbar />
        <main id="main" class="flex-1">
            <RouterView />
        </main>
        <MarketingFooter />
    </div>
</template>
