<!-- src/views/conversations/ConversationShow.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useConversationStore } from '@/stores/conversation';
import { useMessageStore } from '@/stores/message';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';

// Import components
import MessageBubble from '@/components/messages/MessageBubble.vue';
import MessageComposer from '@/components/messages/MessageComposer.vue';
import InternalNote from '@/components/messages/InternalNote.vue';

const route = useRoute();
const router = useRouter();
const conversationStore = useConversationStore();
const messageStore = useMessageStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showNoteDialog = ref(false);
const showActionsMenu = ref(false);
// const newMessage = ref('');
const noteContent = ref('');
const messagesContainer = ref(null);
const messagesEnd = ref(null);

const conversationFilters = reactive({
    search: '',
});

// ============================================
// COMPUTED
// ============================================

const loadingConversations = computed(() => conversationStore.loading);
const loadingMessages = computed(() => messageStore.loading);
const sending = computed(() => messageStore.sending);
const conversations = computed(() => conversationStore.conversations);
const currentConversation = computed(() => conversationStore.currentConversation);
const messages = computed(() => messageStore.messages);

const canReply = computed(() => {
    return authStore.hasPermission('conversations.reply');
});

// ============================================
// METHODS
// ============================================

const loadConversations = async () => {
    await conversationStore.fetchConversations({
        search: conversationFilters.search,
        per_page: 50,
    });
};

const selectConversation = async (conversation) => {
    router.push(`/conversations/${conversation.id}`);
};

const loadConversation = async (id) => {
    await conversationStore.fetchConversation(id);
    await loadMessages(id);
    scrollToBottom();
};

const loadMessages = async (conversationId) => {
    await messageStore.fetchMessages(conversationId);
};


const sendMessage = async (messageContent) => {
    // console.log('🟢 ConversationShow: sendMessage called');
    // console.log('🟢 ConversationShow: messageContent:', messageContent);
    // console.log('🟢 ConversationShow: currentConversation:', currentConversation.value);

    if (!messageContent?.trim()) {
        console.log('🟢 ConversationShow: Message is empty');
        return;
    }

    if (!currentConversation.value) {
        console.log('🟢 ConversationShow: No conversation selected');
        // toast.error('No conversation selected');
        return;
    }

    // console.log('🟢 ConversationShow: Sending to store...');

    try {
        const result = await messageStore.sendMessage(
            currentConversation.value.id,
            { content: messageContent.trim() }
        );
        console.log('🟢 ConversationShow: Message sent successfully:', result);
        scrollToBottom();
        await loadConversations();
    } catch (error) {
        console.error('🟢 ConversationShow: Error sending message:', error);
    }
};

const submitNote = async () => {
    if (!noteContent.value.trim() || !currentConversation.value) return;

    try {
        await messageStore.addNote(currentConversation.value.id, {
            content: noteContent.value.trim(),
        });
        noteContent.value = '';
        showNoteDialog.value = false;
        // toast.success('Note added successfully 📝');
        scrollToBottom();
    } catch (error) {
        // Error handled in store
    }
};

const handleResolve = async () => {
    if (!currentConversation.value) return;
    try {
        await conversationStore.resolveConversation(currentConversation.value.id);
        // toast.success('Conversation resolved successfully ✅');
        await loadConversation(currentConversation.value.id);
    } catch (error) {
        // Error handled in store
    }
};

const handleReopen = async () => {
    if (!currentConversation.value) return;
    try {
        await conversationStore.reopenConversation(currentConversation.value.id);
        // toast.success('Conversation reopened successfully 🔄');
        await loadConversation(currentConversation.value.id);
    } catch (error) {
        // Error handled in store
    }
};

const handleClose = async () => {
    if (!currentConversation.value) return;
    if (!confirm('Are you sure you want to close this conversation?')) return;

    try {
        await conversationStore.closeConversation(currentConversation.value.id);
        // toast.success('Conversation closed successfully 🔒');
        await loadConversation(currentConversation.value.id);
    } catch (error) {
        // Error handled in store
    }
};

const searchConversations = () => {
    loadConversations();
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesEnd.value) {
            messagesEnd.value.scrollIntoView({ behavior: 'smooth' });
        }
    });
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(async () => {
    await loadConversations();

    const conversationId = route.params.id;
    if (conversationId) {
        await loadConversation(conversationId);
    }
});

// Watch for route changes
watch(() => route.params.id, async (newId) => {
    if (newId) {
        await loadConversation(newId);
    }
});

// Watch messages for scrolling
watch(() => messages.value.length, () => {
    scrollToBottom();
});
</script>

<template>
    <div class="flex h-[calc(100vh-120px)] bg-surface-50 dark:bg-surface-950">
        <!-- ============================================ -->
        <!-- CONVERSATION LIST SIDEBAR -->
        <!-- ============================================ -->
        <div class="w-80 border-r border-surface-200 dark:border-surface-700 flex flex-col">
            <div class="p-4 border-b border-surface-200 dark:border-surface-700">
                <h2 class="text-lg font-bold">Conversations</h2>
                <InputText v-model="conversationFilters.search" placeholder="Search..." class="w-full mt-2"
                    @input="searchConversations" />
            </div>
            <div class="flex-1 overflow-y-auto">
                <div v-if="loadingConversations" class="p-4 text-center">
                    <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
                </div>
                <div v-for="conv in conversations" :key="conv.id"
                    class="p-3 border-b border-surface-100 dark:border-surface-800 cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    :class="{ 'bg-primary-50 dark:bg-primary-950': currentConversation?.id === conv.id }"
                    @click="selectConversation(conv)">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium truncate">
                                {{ conv.customer?.full_name || 'Unknown' }}
                            </div>
                            <div class="text-sm text-surface-500 truncate">
                                {{ conv.subject || 'No subject' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1 ml-2">
                            <Tag v-if="conv.status === 'open'" value="" severity="info" rounded class="w-2 h-2 p-0" />
                            <Tag v-else-if="conv.status === 'pending'" value="" severity="warning" rounded
                                class="w-2 h-2 p-0" />
                            <span class="text-xs text-surface-400 whitespace-nowrap">
                                {{ conv.time_ago }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <Tag :value="conv.status_label" :severity="conv.status_color" size="small" />
                        <Tag :value="conv.priority_label" :severity="conv.priority_color" size="small" />
                    </div>
                </div>
                <div v-if="!loadingConversations && conversations.length === 0"
                    class="p-8 text-center text-surface-500">
                    <i class="pi pi-inbox text-4xl mb-2 block"></i>
                    <p>No conversations found</p>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MAIN CHAT AREA -->
        <!-- ============================================ -->
        <div class="flex-1 flex flex-col">
            <!-- Chat Header -->
            <div v-if="currentConversation"
                class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Avatar :label="currentConversation.customer?.full_name?.charAt(0) || '?'" shape="circle"
                        size="large" />
                    <div>
                        <div class="font-bold">{{ currentConversation.customer?.full_name || 'Unknown' }}</div>
                        <div class="text-sm text-surface-500">{{ currentConversation.customer?.email || 'No email' }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Tag :value="currentConversation.status_label" :severity="currentConversation.status_color" />
                    <Tag :value="currentConversation.priority_label" :severity="currentConversation.priority_color" />

                    <!-- Resolve Button -->
                    <Button
                        v-if="canReply && (currentConversation.status === 'open' || currentConversation.status === 'pending')"
                        icon="pi pi-check" label="Resolve" severity="success" size="small" @click="handleResolve" />

                    <!-- Reopen Button -->
                    <Button
                        v-if="canReply && (currentConversation.status === 'resolved' || currentConversation.status === 'closed')"
                        icon="pi pi-refresh" label="Reopen" severity="warning" size="small" @click="handleReopen" />

                    <!-- Close Button -->
                    <Button v-if="canReply && currentConversation.status !== 'closed'" icon="pi pi-times" label="Close"
                        severity="secondary" size="small" @click="handleClose" />

                    <Button icon="pi pi-ellipsis-v" severity="secondary" text rounded
                        @click="showActionsMenu = !showActionsMenu" />
                </div>
            </div>

            <!-- Messages Area -->
            <div v-if="currentConversation" class="flex-1 overflow-y-auto p-4 space-y-2" ref="messagesContainer">
                <!-- Loading -->
                <div v-if="loadingMessages" class="text-center py-8">
                    <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
                </div>

                <!-- Messages -->
                <template v-for="message in messages" :key="message.id">
                    <!-- Internal Note -->
                    <InternalNote v-if="message.is_internal_note" :note="message" />

                    <!-- System Event -->
                    <div v-else-if="message.sender.type === 'system'" class="flex justify-center my-2">
                        <div
                            class="bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 px-4 py-2 rounded-lg text-sm">
                            <i class="pi pi-info-circle mr-2"></i>
                            {{ message.content }}
                        </div>
                    </div>

                    <!-- Regular Message -->
                    <MessageBubble v-else :message="message" />
                </template>

                <!-- Empty State -->
                <div v-if="!loadingMessages && messages.length === 0" class="text-center py-12 text-surface-500">
                    <i class="pi pi-comments text-4xl mb-2 block"></i>
                    <p>No messages yet</p>
                    <p class="text-sm">Start the conversation by sending a message</p>
                </div>

                <!-- Scroll Anchor -->
                <div ref="messagesEnd"></div>
            </div>

            <!-- Message Composer -->
            <!-- Message Composer -->
            <MessageComposer v-if="currentConversation && canReply" @send="sendMessage" @addNote="showNoteDialog = true"
                :disabled="currentConversation.status === 'closed'" :sending="sending" />

            <!-- Conversation Closed Message -->
            <div v-else-if="currentConversation && currentConversation.status === 'closed'"
                class="p-4 border-t border-surface-200 dark:border-surface-700 text-center text-surface-500">
                <i class="pi pi-lock mr-2"></i>
                This conversation is closed
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ADD NOTE DIALOG -->
        <!-- ============================================ -->
        <Dialog v-model:visible="showNoteDialog" header="Add Internal Note" :style="{ width: '500px' }" modal>
            <div class="space-y-4">
                <Textarea v-model="noteContent" placeholder="Write your internal note here..." class="w-full"
                    rows="4" />
                <div class="text-sm text-surface-500">
                    <i class="pi pi-info-circle mr-1"></i>
                    Internal notes are only visible to team members
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showNoteDialog = false" />
                <Button label="Add Note" icon="pi pi-plus" severity="primary" :loading="sending" @click="submitNote"
                    :disabled="!noteContent.trim()" />
            </template>
        </Dialog>

        <!-- Toast -->
        <!-- <Toast /> -->
    </div>
</template>

<style scoped>
:deep(.p-textarea) {
    resize: none;
}

/* Scrollbar styling */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: var(--surface-ground);
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: var(--surface-border);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: var(--surface-hover);
}
</style>
