<!-- src/components/messages/InternalNote.vue -->
<script setup>
defineProps({
    note: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="flex justify-center my-2">
        <div
            class="bg-yellow-50 dark:bg-yellow-950 text-yellow-800 dark:text-yellow-200 px-4 py-2 rounded-lg text-sm max-w-[80%]">
            <div class="flex items-center gap-2">
                <i class="pi pi-lock text-xs"></i>
                <span class="font-medium">Internal Note</span>
            </div>
            <div class="mt-1">{{ note.content }}</div>
            <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                {{ note.sender.name }} • {{ note.formatted_date }}
            </div>
        </div>
    </div>
</template>
