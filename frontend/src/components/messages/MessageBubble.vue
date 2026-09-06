<!-- src/components/messages/MessageBubble.vue -->
<script setup>
defineProps({
    message: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="flex" :class="{
        'justify-end': message.sender.type === 'agent',
        'justify-start': message.sender.type === 'customer' || message.sender.type === 'ai',
    }">
        <div class="flex items-start gap-2 max-w-[70%]" :class="{
            'flex-row-reverse': message.sender.type === 'agent',
        }">
            <Avatar :label="message.sender.name?.charAt(0) || '?'" :image="message.sender.avatar_url" shape="circle"
                size="small" :class="{
                    'bg-primary text-white': message.sender.type === 'agent',
                    'bg-success text-white': message.sender.type === 'ai',
                }" />
            <div>
                <div class="flex items-center gap-2" :class="{
                    'justify-end': message.sender.type === 'agent',
                }">
                    <span class="text-sm font-medium">{{ message.sender.name }}</span>
                    <span class="text-xs text-surface-400">{{ message.formatted_date }}</span>
                </div>
                <div class="rounded-lg px-4 py-2 mt-1 break-words" :class="{
                    'bg-primary text-white': message.sender.type === 'agent',
                    'bg-surface-100 dark:bg-surface-800': message.sender.type === 'customer',
                    'bg-success-50 dark:bg-success-950 text-success-900 dark:text-success-100': message.sender.type === 'ai',
                }">
                    {{ message.content }}
                </div>
                <div v-if="message.metadata?.model" class="text-xs text-surface-400 mt-1">
                    <i class="pi pi-robot mr-1"></i>
                    {{ message.metadata.model }}
                </div>
            </div>
        </div>
    </div>
</template>
