<!-- src/components/ai/AIStreamingMessage.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
    isStreaming: {
        type: Boolean,
        default: false,
    },
    metadata: {
        type: Object,
        default: () => ({}),
    },
    tokens: {
        type: Number,
        default: 0,
    },
    createdAt: {
        type: String,
        default: null,
    },
});

const formattedTime = computed(() => {
    if (props.createdAt) {
        return new Date(props.createdAt).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }
    return new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <div class="flex justify-start">
        <div class="flex items-start gap-2 max-w-[70%]">
            <!-- AI Avatar -->
            <Avatar label="AI" shape="circle" size="small" class="bg-success text-white" />

            <div class="flex-1">
                <!-- Header -->
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-success-700 dark:text-success-300">
                        AI Assistant
                    </span>
                    <span class="text-xs text-surface-400">
                        {{ formattedTime }}
                    </span>
                    <span v-if="isStreaming" class="text-xs text-success-500 animate-pulse">
                        ● Thinking...
                    </span>
                </div>

                <!-- Content -->
                <div
                    class="rounded-lg px-4 py-2 mt-1 break-words bg-success-50 dark:bg-success-950 text-success-900 dark:text-success-100">
                    <!-- Streaming content with cursor -->
                    <span v-if="isStreaming">
                        {{ content }}
                        <span class="inline-block w-2 h-4 bg-success-600 animate-pulse ml-1 align-middle"></span>
                    </span>
                    <!-- Complete content -->
                    <span v-else>{{ content }}</span>
                </div>

                <!-- Metadata -->
                <div v-if="metadata?.model && !isStreaming" class="text-xs text-surface-400 mt-1">
                    <i class="pi pi-robot mr-1"></i>
                    {{ metadata.model }}
                    <span v-if="metadata.tokens" class="ml-2">
                        • {{ metadata.tokens }} tokens
                    </span>
                    <span v-if="metadata.knowledge_used" class="ml-2 text-success">
                        • Knowledge used
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
