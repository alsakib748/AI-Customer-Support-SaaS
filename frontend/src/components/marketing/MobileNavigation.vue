<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { X } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import ThemeSwitcher from './ThemeSwitcher.vue';
import { navLinks, resourceLinks, AUTH } from '@/data/marketing';

defineProps({ id: { type: String, default: 'mobile-menu' } });
const emit = defineEmits(['close']);

const panel = ref(null);

function onKey(event) {
    if (event.key === 'Escape') emit('close');
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    nextTick(() => panel.value?.focus());
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[60] lg:hidden">
            <Transition appear enter-active-class="transition-opacity duration-200 ease-out" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150 ease-in" leave-to-class="opacity-0">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" aria-hidden="true" @click="emit('close')"></div>
            </Transition>

            <Transition appear enter-active-class="transition-transform duration-300 ease-out" enter-from-class="translate-x-full" leave-active-class="transition-transform duration-200 ease-in" leave-to-class="translate-x-full">
                <div :id="id" ref="panel" role="dialog" aria-modal="true" aria-label="Menu" tabindex="-1" class="absolute top-0 right-0 flex h-full w-[85vw] max-w-sm flex-col bg-page shadow-soft outline-none">
                    <div class="flex items-center justify-between border-b border-line px-4 py-4">
                        <RouterLink to="/" class="flex items-center gap-2.5 text-lg font-bold tracking-tight text-ink" @click="emit('close')">
                            <img src="/SupportAI-brand-assets/svg/symbol.svg" alt="SupportAI logo" class="h-8 w-auto" />
                            SupportAI
                        </RouterLink>
                        <button type="button" class="flex size-10 items-center justify-center rounded-xl border border-line bg-card text-ink" aria-label="Close menu" @click="emit('close')">
                            <X class="size-5" aria-hidden="true" />
                        </button>
                    </div>

                    <nav aria-label="Mobile" class="flex flex-1 flex-col gap-1 overflow-y-auto px-4 py-2">
                        <RouterLink v-for="link in [...navLinks, ...resourceLinks]" :key="link.label" :to="link.to" class="border-b border-line py-4 text-[17px] font-medium text-ink" @click="emit('close')">
                            {{ link.label }}
                        </RouterLink>
                    </nav>

                    <div class="border-t border-line px-4 py-5">
                        <div class="flex flex-col gap-3">
                            <BaseButton variant="secondary" :to="AUTH.login" block @click="emit('close')">Sign in</BaseButton>
                            <BaseButton variant="primary" :to="AUTH.register" block @click="emit('close')">Get started free</BaseButton>
                        </div>
                        <div class="mt-6"><ThemeSwitcher inline /></div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
