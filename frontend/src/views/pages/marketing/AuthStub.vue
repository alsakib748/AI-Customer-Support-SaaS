<script setup>
// STUB so this project runs on its own. In your real app, delete this file and the
// /login and /register stub routes, and keep your existing authentication pages.
import { RouterLink, useRoute } from 'vue-router';

defineProps({ mode: { type: String, default: 'Sign in' } });
const route = useRoute();
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-page px-4">
        <div class="max-w-md rounded-[22px] border border-line bg-card p-8 text-center">
            <h1 class="text-2xl font-bold tracking-tight">{{ mode }}</h1>
            <p class="mt-3 text-body">
                Placeholder page. Your existing {{ mode.toLowerCase() }} route replaces this.
                <span v-if="route.query.plan">
                    Selected plan: <strong class="text-ink">{{ route.query.plan }}</strong
                    >.</span
                >
            </p>
            <RouterLink to="/" class="mt-6 inline-block font-semibold text-accent hover:underline">Back to the website</RouterLink>
        </div>
    </main>
</template>
