<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ route.meta.title }}</h1>
            <p class="text-surface-600 dark:text-surface-400">Platform scope · Super Admin</p>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="pi pi-wrench text-4xl text-surface-400 mb-4" style="font-size: 3rem"></i>
                    <h2 class="text-xl font-semibold text-surface-800 dark:text-surface-0 mb-2">{{ route.meta.title }} coming soon</h2>
                    <p class="text-surface-500 dark:text-surface-400 max-w-md">
                        This Super Admin module is not implemented yet. The menu and route are wired up (gated by the <code class="font-mono text-primary">{{ route.meta.permission }}</code> permission).
                    </p>
                </div>
            </template>
        </Card>
    </div>
</template>
