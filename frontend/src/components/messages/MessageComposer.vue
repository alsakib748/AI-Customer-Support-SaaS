<!-- src/components/messages/MessageComposer.vue -->
<script setup>
import { ref } from 'vue';

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    sending: {
        type: Boolean,
        default: false,
    },
    showNoteButton: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['send', 'addNote']);

const message = ref('');

// const send = () => {
//     if (message.value.trim()) {
//         emit('send', message.value.trim());
//         message.value = '';
//     }
// };

const send = () => {
    console.log('🔵 MessageComposer: send() called');
    console.log('🔵 MessageComposer: message value:', message.value);
    console.log('🔵 MessageComposer: message trimmed:', message.value.trim());
    console.log('🔵 MessageComposer: disabled:', props.disabled);
    console.log('🔵 MessageComposer: sending:', props.sending);

    if (message.value.trim()) {
        console.log('🔵 MessageComposer: Emitting send event with:', message.value.trim());
        emit('send', message.value.trim());
        message.value = '';
    } else {
        console.log('🔵 MessageComposer: Message is empty, not sending');
    }
};

</script>

<template>
    <div class="p-4 border-t border-surface-200 dark:border-surface-700">
        <div class="flex gap-2">
            <div class="flex-1">
                <Textarea v-model="message" placeholder="Type a message..." class="w-full" rows="2"
                    @keydown.ctrl.enter="send" @keydown.meta.enter="send" :disabled="disabled || sending" />
            </div>
            <div class="flex flex-col gap-2">
                <Button icon="pi pi-send" label="Send" severity="primary" :loading="sending" @click="send"
                    :disabled="!message.trim() || disabled || sending" />
                <Button v-if="showNoteButton" icon="pi pi-lock" label="Add Note" severity="secondary" outlined
                    size="small" @click="$emit('addNote')" :disabled="disabled" />
            </div>
        </div>
        <div class="text-xs text-surface-400 mt-1">
            Press Ctrl+Enter to send
        </div>
    </div>
</template>
