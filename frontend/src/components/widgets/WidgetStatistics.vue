<!-- src/components/widgets/WidgetStatistics.vue -->
<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
        default: () => ({}),
    },
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
            <template #content>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary">{{ stats.total_sessions || 0 }}</div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">Total Sessions</div>
                </div>
            </template>
        </Card>
        <Card>
            <template #content>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success">{{ stats.converted_sessions || 0 }}</div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">Converted</div>
                </div>
            </template>
        </Card>
        <Card>
            <template #content>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info">{{ stats.messages_sent || 0 }}</div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">Messages Sent</div>
                </div>
            </template>
        </Card>
        <Card>
            <template #content>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning">{{ stats.conversation_started || 0 }}</div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">Conversations</div>
                </div>
            </template>
        </Card>
    </div>
</template>
