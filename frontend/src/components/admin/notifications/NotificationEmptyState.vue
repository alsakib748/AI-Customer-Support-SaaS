<script setup>
defineProps({
    icon: { type: String, default: 'pi pi-inbox' },
    title: { type: String, default: 'No notifications' },
    message: { type: String, default: 'You are all caught up.' }
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center py-12 px-4">
        <div class="h-14 w-14 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mb-3">
            <i :class="icon" class="text-xl text-surface-400 dark:text-surface-500"></i>
        </div>
        <div class="font-semibold text-surface-800 dark:text-surface-100">{{ title }}</div>
        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1 max-w-sm">{{ message }}</p>
    </div>
</template>
