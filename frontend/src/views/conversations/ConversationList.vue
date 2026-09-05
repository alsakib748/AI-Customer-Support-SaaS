<!-- src/views/conversations/ConversationList.vue -->

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useConversationStore } from '@/stores/conversation';
import { useCustomerStore } from '@/stores/customer';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';

const conversationStore = useConversationStore();
const customerStore = useCustomerStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showCreateDialog = ref(false);
const showDetailsDialog = ref(false);
const selectedConversation = ref(null);
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    status: null,
    priority: null,
    channel: null,
    assigned_user_id: null,
    unassigned: false,
    sort: 'last_message_at',
    direction: 'desc',
    per_page: 20,
});

const createForm = reactive({
    customer_id: null,
    subject: '',
    channel: 'web',
    priority: 'normal',
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => conversationStore.loading);
const saving = computed(() => conversationStore.saving);
const conversations = computed(() => conversationStore.conversations);
const totalConversations = computed(() => conversationStore.totalConversations);
const openCount = computed(() => conversationStore.openCount);
const pendingCount = computed(() => conversationStore.pendingCount);
const resolvedCount = computed(() => conversationStore.resolvedCount);
const customers = computed(() => customerStore.customers);

const canCreateConversations = computed(() => {
    return authStore.hasPermission('conversations.create');
});

const canUpdateConversations = computed(() => {
    return authStore.hasPermission('conversations.update');
});

const canDeleteConversations = computed(() => {
    return authStore.hasPermission('conversations.delete');
});

const statusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Pending', value: 'pending' },
    { label: 'Resolved', value: 'resolved' },
    { label: 'Closed', value: 'closed' },
];

const priorityOptions = [
    { label: 'Low', value: 'low' },
    { label: 'Normal', value: 'normal' },
    { label: 'High', value: 'high' },
    { label: 'Urgent', value: 'urgent' },
];

const channelOptions = [
    { label: 'Website', value: 'web' },
    { label: 'API', value: 'api' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    await Promise.all([
        conversationStore.fetchConversations({ ...filters }),
        conversationStore.fetchStatistics(),
        customerStore.fetchCustomers({ per_page: 100 }), // Load customers for dropdown
    ]);
};

const refreshData = () => {
    loadData();
};

const applyFilters = () => {
    conversationStore.filters = { ...filters };
    conversationStore.fetchConversations();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: null,
        priority: null,
        channel: null,
        assigned_user_id: null,
        unassigned: false,
        sort: 'last_message_at',
        direction: 'desc',
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    conversationStore.fetchConversations({ ...filters });
};

const onSortChange = (event) => {
    filters.sort = event.sortField;
    filters.direction = event.sortOrder === 1 ? 'asc' : 'desc';
    applyFilters();
};

const viewConversation = (conversation) => {
    selectedConversation.value = conversation;
    showDetailsDialog.value = true;
};

const handleCreate = async () => {
    try {
        await conversationStore.createConversation(createForm);
        showCreateDialog.value = false;
        resetCreateForm();
        await loadData();
    } catch (error) {
        // Error handled in store
    }
};

const resetCreateForm = () => {
    Object.assign(createForm, {
        customer_id: null,
        subject: '',
        channel: 'web',
        priority: 'normal',
    });
};

const handleResolve = async (conversation) => {
    try {
        await conversationStore.resolveConversation(conversation.id);
        await loadData();
        if (selectedConversation.value?.id === conversation.id) {
            selectedConversation.value = conversationStore.currentConversation;
        }
    } catch (error) {
        // Error handled in store
    }
};

const handleReopen = async (conversation) => {
    try {
        await conversationStore.reopenConversation(conversation.id);
        await loadData();
        if (selectedConversation.value?.id === conversation.id) {
            selectedConversation.value = conversationStore.currentConversation;
        }
    } catch (error) {
        // Error handled in store
    }
};

const handleAssignToMe = async (conversation) => {
    try {
        await conversationStore.assignConversation(conversation.id, authStore.user.id);
        await loadData();
        if (selectedConversation.value?.id === conversation.id) {
            selectedConversation.value = conversationStore.currentConversation;
        }
    } catch (error) {
        // Error handled in store
    }
};

const handleUnassign = async (conversation) => {
    try {
        await conversationStore.unassignConversation(conversation.id);
        await loadData();
        if (selectedConversation.value?.id === conversation.id) {
            selectedConversation.value = conversationStore.currentConversation;
        }
    } catch (error) {
        // Error handled in store
    }
};

const confirmDelete = (conversation) => {
    if (confirm(`Are you sure you want to delete conversation "${conversation.subject || 'Untitled'}"?`)) {
        conversationStore.deleteConversation(conversation.id);
    }
};

const getChannelIcon = (channel) => {
    const icons = {
        web: 'pi pi-globe',
        api: 'pi pi-server',
        email: 'pi pi-envelope',
        whatsapp: 'pi pi-whatsapp',
        messenger: 'pi pi-facebook',
    };
    return icons[channel] || 'pi pi-circle';
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getFieldError = (field) => {
    return conversationStore.getFieldError(field);
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
});

watch(() => filters.search, () => {
    applyFilters();
});
</script>

<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Conversations</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage customer conversations and support tickets</p>
            </div>
            <div class="flex gap-3">
                <Button v-if="canCreateConversations" label="New Conversation" icon="pi pi-plus" severity="primary"
                    @click="showCreateDialog = true" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalConversations }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-info">{{ openCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Open</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning">{{ pendingCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Pending</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">{{ resolvedCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Resolved</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="filters.search" placeholder="Search conversations..." class="w-full"
                    @input="applyFilters" />
            </div>
            <div class="w-40">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-40">
                <Select v-model="filters.priority" :options="priorityOptions" optionLabel="label" optionValue="value"
                    placeholder="Priority" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-40">
                <Select v-model="filters.channel" :options="channelOptions" optionLabel="label" optionValue="value"
                    placeholder="Channel" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="filters.unassigned" binary @change="applyFilters" />
                <label class="text-sm">Unassigned</label>
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <!-- Conversations Table -->
        <DataTable :value="conversations" :loading="loading" paginator :rows="filters.per_page"
            :totalRecords="totalConversations" :lazy="true" @page="onPageChange" @sort="onSortChange" class="w-full"
            v-model:sortField="filters.sort" v-model:sortOrder="sortOrder">
            <Column field="customer.full_name" header="Customer" sortable>
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.customer?.full_name || 'Unknown' }}</div>
                        <div class="text-sm text-surface-500">{{ data.customer?.email || 'No email' }}</div>
                    </div>
                </template>
            </Column>

            <Column field="subject" header="Subject" sortable>
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.subject || 'No subject' }}</div>
                        <div class="text-sm text-surface-500">{{ data.time_ago }}</div>
                    </div>
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>

            <Column field="priority" header="Priority" sortable>
                <template #body="{ data }">
                    <Tag :value="data.priority_label" :severity="data.priority_color" />
                </template>
            </Column>

            <Column field="channel" header="Channel" sortable>
                <template #body="{ data }">
                    <span class="flex items-center gap-2">
                        <i :class="getChannelIcon(data.channel)" />
                        {{ data.channel_label }}
                    </span>
                </template>
            </Column>

            <Column field="assigned_user_id" header="Assigned" sortable>
                <template #body="{ data }">
                    <span v-if="data.assigned_user_id" class="text-primary">
                        <i class="pi pi-user" />
                        {{ data.assigned_user_id }}
                    </span>
                    <span v-else class="text-surface-400">Unassigned</span>
                </template>
            </Column>

            <Column field="last_message_at" header="Last Activity" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.last_message_at) }}
                </template>
            </Column>

            <Column header="Actions" style="width: 150px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewConversation(data)"
                            tooltip="View" />
                        <Button v-if="canUpdateConversations && data.status !== 'resolved' && data.status !== 'closed'"
                            icon="pi pi-check" severity="success" text rounded @click="handleResolve(data)"
                            tooltip="Resolve" />
                        <Button
                            v-if="canUpdateConversations && (data.status === 'resolved' || data.status === 'closed')"
                            icon="pi pi-refresh" severity="warning" text rounded @click="handleReopen(data)"
                            tooltip="Reopen" />
                        <Button v-if="canDeleteConversations" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmDelete(data)" tooltip="Delete" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create Conversation Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="New Conversation" :style="{ width: '500px' }" modal>
            <form @submit.prevent="handleCreate" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Customer *</label>
                    <Select v-model="createForm.customer_id" :options="customers" optionLabel="display_name"
                        optionValue="id" class="w-full" :class="{ 'p-invalid': getFieldError('customer_id') }"
                        placeholder="Select Customer" filter />
                    <small v-if="getFieldError('customer_id')" class="text-red-500">
                        {{ getFieldError('customer_id') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Subject</label>
                    <InputText v-model="createForm.subject" class="w-full"
                        placeholder="Brief description of the issue" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Channel *</label>
                    <Select v-model="createForm.channel" :options="channelOptions" optionLabel="label"
                        optionValue="value" class="w-full" :class="{ 'p-invalid': getFieldError('channel') }"
                        placeholder="Select Channel" />
                    <small v-if="getFieldError('channel')" class="text-red-500">
                        {{ getFieldError('channel') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Priority</label>
                    <Select v-model="createForm.priority" :options="priorityOptions" optionLabel="label"
                        optionValue="value" class="w-full" placeholder="Select Priority" />
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showCreateDialog = false" />
                <Button label="Create" icon="pi pi-plus" severity="primary" :loading="saving" @click="handleCreate" />
            </template>
        </Dialog>

        <!-- Conversation Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" header="Conversation Details" :style="{ width: '800px' }" modal
            :maximizable="true">
            <div v-if="selectedConversation" class="space-y-4">
                <!-- Conversation Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-bold">{{ selectedConversation.subject || 'No Subject' }}</h3>
                        <div class="flex items-center gap-3 mt-1">
                            <Tag :value="selectedConversation.status_label"
                                :severity="selectedConversation.status_color" />
                            <Tag :value="selectedConversation.priority_label"
                                :severity="selectedConversation.priority_color" />
                            <span class="text-sm text-surface-500">{{ selectedConversation.channel_label }}</span>
                            <span class="text-sm text-surface-500">
                                {{ selectedConversation.customer?.full_name || 'Unknown' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="canUpdateConversations && selectedConversation.status !== 'resolved' && selectedConversation.status !== 'closed'"
                            icon="pi pi-check" label="Resolve" severity="success" size="small"
                            @click="handleResolve(selectedConversation)" />
                        <Button
                            v-if="canUpdateConversations && (selectedConversation.status === 'resolved' || selectedConversation.status === 'closed')"
                            icon="pi pi-refresh" label="Reopen" severity="warning" size="small"
                            @click="handleReopen(selectedConversation)" />
                        <Button icon="pi pi-times" severity="secondary" text @click="showDetailsDialog = false" />
                    </div>
                </div>

                <Divider />

                <!-- Conversation Info -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-surface-500">Customer</label>
                        <div class="font-medium">{{ selectedConversation.customer?.full_name || 'Unknown' }}</div>
                        <div class="text-sm text-surface-500">{{ selectedConversation.customer?.email || 'No email' }}
                        </div>
                        <div class="text-sm text-surface-500">{{ selectedConversation.customer?.phone || 'No phone' }}
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Assigned To</label>
                        <div class="font-medium">
                            {{ selectedConversation.assigned_user_id || 'Unassigned' }}
                        </div>
                        <div class="text-sm text-surface-500">Messages: {{ selectedConversation.messages_count || 0 }}
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Timeline</label>
                        <div class="text-sm">Started: {{ formatDate(selectedConversation.started_at) }}</div>
                        <div class="text-sm" v-if="selectedConversation.resolved_at">
                            Resolved: {{ formatDate(selectedConversation.resolved_at) }}
                        </div>
                        <div class="text-sm" v-if="selectedConversation.closed_at">
                            Closed: {{ formatDate(selectedConversation.closed_at) }}
                        </div>
                    </div>
                </div>

                <!-- Messages Placeholder -->
                <div class="border rounded-lg p-4 bg-surface-50 dark:bg-surface-800">
                    <div class="text-center text-surface-500 py-8">
                        <i class="pi pi-comments text-4xl mb-2 block"></i>
                        <p>Messages will appear here</p>
                        <p class="text-sm">(Message module coming soon)</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="flex gap-2">
                    <Button v-if="canUpdateConversations && !selectedConversation.assigned_user_id"
                        icon="pi pi-user-plus" label="Assign to Me" severity="primary" size="small"
                        @click="handleAssignToMe(selectedConversation)" />
                    <Button v-if="canUpdateConversations && selectedConversation.assigned_user_id"
                        icon="pi pi-user-minus" label="Unassign" severity="secondary" size="small"
                        @click="handleUnassign(selectedConversation)" />
                </div>
            </div>
        </Dialog>

        <!-- Toast -->
        <Toast />
    </div>
</template>


<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--surface-ground);
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}
</style>
