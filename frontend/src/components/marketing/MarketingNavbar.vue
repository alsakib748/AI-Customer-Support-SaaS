<script setup>
import { ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { ChevronDown, Menu, X } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import ThemeSwitcher from './ThemeSwitcher.vue';
import MobileNavigation from './MobileNavigation.vue';
import { useDismiss } from '@/composables/useDismiss';
import { navLinks, resourceLinks, AUTH } from '@/data/marketing';

const route = useRoute();
const menuOpen = ref(false);
const resourcesOpen = ref(false);
const resourcesRoot = ref(null);
useDismiss(resourcesOpen, resourcesRoot);

watch(
    () => route.fullPath,
    () => {
        menuOpen.value = false;
        resourcesOpen.value = false;
    }
);

function onKey(e) {
    if (e.key === 'Escape') menuOpen.value = false;
}
</script>

<template>
    <header class="sticky top-0 z-50 border-b border-line bg-nav backdrop-blur" @keydown="onKey">
        <div class="mx-auto flex h-[72px] w-full max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
            <RouterLink to="/" class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-ink" aria-label="SupportAI home">
                <img src="/SupportAI-brand-assets/svg/symbol.svg" alt="SupportAI logo" class="h-9 w-auto" />
                SupportAI
            </RouterLink>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                <RouterLink v-for="link in navLinks" :key="link.label" :to="link.to" class="rounded-[10px] px-3.5 py-2 text-[15px] font-medium text-body transition-colors hover:bg-alt hover:text-ink">
                    {{ link.label }}
                </RouterLink>

                <div ref="resourcesRoot" class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-[10px] px-3.5 py-2 text-[15px] font-medium text-body transition-colors hover:bg-alt hover:text-ink"
                        :aria-expanded="resourcesOpen"
                        aria-haspopup="true"
                        @click="resourcesOpen = !resourcesOpen"
                    >
                        Resources
                        <ChevronDown class="size-4 transition-transform" :class="resourcesOpen && 'rotate-180'" aria-hidden="true" />
                    </button>
                    <div v-if="resourcesOpen" class="absolute left-0 mt-2 w-64 rounded-2xl border border-line bg-card p-2 shadow-soft">
                        <RouterLink v-for="r in resourceLinks" :key="r.label" :to="r.to" class="flex flex-col rounded-xl px-3 py-2.5 transition-colors hover:bg-alt">
                            <span class="text-[15px] font-semibold text-ink">{{ r.label }}</span>
                            <span class="text-sm text-muted">{{ r.description }}</span>
                        </RouterLink>
                    </div>
                </div>
            </nav>

            <div class="flex items-center gap-2">
                <ThemeSwitcher />
                <BaseButton class="hidden lg:inline-flex" variant="ghost" size="sm" :to="AUTH.login">Sign in</BaseButton>
                <BaseButton class="hidden lg:inline-flex" variant="primary" size="sm" :to="AUTH.register">Get started free</BaseButton>
                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-xl border border-line bg-card text-ink lg:hidden"
                    :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-menu"
                    @click="menuOpen = !menuOpen"
                >
                    <component :is="menuOpen ? X : Menu" class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <MobileNavigation v-if="menuOpen" id="mobile-menu" @close="menuOpen = false" />
    </header>
</template>
