<!-- src/views/tickets/Tickets.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useTicketStore } from '@/stores/ticket';
import { useCustomerStore } from '@/stores/customer';
import { useConversationStore } from '@/stores/conversation';
import { useTeamStore } from '@/stores/team';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';

const router = useRouter();
const ticketStore = useTicketStore();
const customerStore = useCustomerStore();
const conversationStore = useConversationStore();
const teamStore = useTeamStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showCreateDialog = ref(false);
const showDetailsDialog = ref(false);
const showCommentDialog = ref(false);
const selectedTicket = ref(null);
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    status: null,
    priority: null,
    type: null,
    source: null,
    assigned_user_id: null,
    unassigned: false,
    overdue: false,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20,
});

const createForm = reactive({
    customer_id: null,
    conversation_id: null,
    subject: '',
    description: '',
    priority: 'normal',
    type: 'general',
    assigned_user_id: null,
});

const commentForm = reactive({
    type: 'reply',
    content: '',
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => ticketStore.loading);
const saving = computed(() => ticketStore.saving);
const tickets = computed(() => ticketStore.tickets);
const comments = computed(() => ticketStore.comments);
const totalTickets = computed(() => ticketStore.totalTickets);
const openCount = computed(() => ticketStore.openCount);
const inProgressCount = computed(() => ticketStore.inProgressCount);
const pendingCount = computed(() => ticketStore.pendingCount);
const resolvedCount = computed(() => ticketStore.resolvedCount);
const overdueCount = computed(() => ticketStore.overdueCount);
const customers = computed(() => customerStore.customers);
const conversations = computed(() => conversationStore.conversations);
const teamMembers = computed(() => teamStore.members);

const canCreateTickets = computed(() => authStore.hasPermission('tickets.create'));
const canUpdateTickets = computed(() => authStore.hasPermission('tickets.update'));
const canDeleteTickets = computed(() => authStore.hasPermission('tickets.delete'));
const canAssignTickets = computed(() => authStore.hasPermission('tickets.assign'));

const statusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'In Progress', value: 'in_progress' },
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

const typeOptions = [
    { label: 'General', value: 'general' },
    { label: 'Technical', value: 'technical' },
    { label: 'Billing', value: 'billing' },
    { label: 'Account', value: 'account' },
    { label: 'Bug', value: 'bug' },
    { label: 'Feature Request', value: 'feature_request' },
    { label: 'Other', value: 'other' },
];

const commentTypeOptions = [
    { label: 'Reply', value: 'reply' },
    { label: 'Internal Note', value: 'internal_note' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    await Promise.all([
        ticketStore.fetchTickets({ ...filters }),
        ticketStore.fetchStatistics(),
        customerStore.fetchCustomers({ per_page: 100 }),
        conversationStore.fetchConversations({ per_page: 50 }),
        teamStore.fetchMembers(),
    ]);
};

const refreshData = () => {
    loadData();
};

const applyFilters = () => {
    ticketStore.filters = { ...filters };
    ticketStore.fetchTickets();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: null,
        priority: null,
        type: null,
        source: null,
        assigned_user_id: null,
        unassigned: false,
        overdue: false,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    ticketStore.fetchTickets({ ...filters });
};

const onSortChange = (event) => {
    filters.sort = event.sortField;
    filters.direction = event.sortOrder === 1 ? 'asc' : 'desc';
    applyFilters();
};

const openCreateDialog = () => {
    showCreateDialog.value = true;
};

const viewTicket = async (ticket) => {
    selectedTicket.value = ticket;
    showDetailsDialog.value = true;
    await ticketStore.fetchComments(ticket.id);
};

const handleCreate = async () => {
    try {
        await ticketStore.createTicket(createForm);
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
        conversation_id: null,
        subject: '',
        description: '',
        priority: 'normal',
        type: 'general',
        assigned_user_id: null,
    });
};

const handleAddComment = async () => {
    if (!selectedTicket.value) return;
    try {
        await ticketStore.addComment(selectedTicket.value.id, commentForm);
        showCommentDialog.value = false;
        commentForm.content = '';
        commentForm.type = 'reply';
        await ticketStore.fetchComments(selectedTicket.value.id);
    } catch (error) {
        // Error handled in store
    }
};

const assignToMe = async (ticket) => {
    try {
        await ticketStore.assignTicket(ticket.id, authStore.user.id);
        await loadData();
    } catch (error) {
        // Error handled in store
    }
};

const unassignTicket = async (ticket) => {
    try {
        await ticketStore.unassignTicket(ticket.id);
        await loadData();
    } catch (error) {
        // Error handled in store
    }
};

const startTicket = async (ticket) => {
    try {
        await ticketStore.startTicket(ticket.id);
        await loadData();
        if (selectedTicket.value?.id === ticket.id) {
            selectedTicket.value = ticketStore.currentTicket;
        }
    } catch (error) {
        // Error handled in store
    }
};

const resolveTicket = async (ticket) => {
    try {
        await ticketStore.resolveTicket(ticket.id);
        await loadData();
        if (selectedTicket.value?.id === ticket.id) {
            selectedTicket.value = ticketStore.currentTicket;
        }
    } catch (error) {
        // Error handled in store
    }
};

const reopenTicket = async (ticket) => {
    try {
        await ticketStore.reopenTicket(ticket.id);
        await loadData();
        if (selectedTicket.value?.id === ticket.id) {
            selectedTicket.value = ticketStore.currentTicket;
        }
    } catch (error) {
        // Error handled in store
    }
};

const closeTicket = async (ticket) => {
    if (!confirm('Are you sure you want to close this ticket?')) return;
    try {
        await ticketStore.closeTicket(ticket.id);
        await loadData();
        if (selectedTicket.value?.id === ticket.id) {
            selectedTicket.value = ticketStore.currentTicket;
            showDetailsDialog.value = false;
        }
    } catch (error) {
        // Error handled in store
    }
};

const confirmDelete = (ticket) => {
    if (confirm(`Are you sure you want to delete ticket "${ticket.ticket_number}"?`)) {
        ticketStore.deleteTicket(ticket.id);
    }
};

const viewConversation = (id) => {
    router.push(`/conversations/${id}`);
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
    return ticketStore.getFieldError(field);
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
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Tickets</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage customer support tickets</p>
            </div>
            <div class="flex gap-3">
                <Button v-if="canCreateTickets" label="New Ticket" icon="pi pi-plus" severity="primary"
                    @click="openCreateDialog" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalTickets }}</div>
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
                        <div class="text-2xl font-bold text-warning">{{ inProgressCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">In Progress</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-secondary">{{ pendingCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Pending</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-danger">{{ overdueCount }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Overdue</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="filters.search" placeholder="Search tickets..." class="w-full"
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
                <Select v-model="filters.type" :options="typeOptions" optionLabel="label" optionValue="value"
                    placeholder="Type" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="filters.unassigned" binary @change="applyFilters" />
                <label class="text-sm">Unassigned</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="filters.overdue" binary @change="applyFilters" />
                <label class="text-sm">Overdue</label>
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <!-- Tickets Table -->
        <DataTable :value="tickets" :loading="loading" paginator :rows="filters.per_page" :totalRecords="totalTickets"
            :lazy="true" @page="onPageChange" @sort="onSortChange" class="w-full" v-model:sortField="filters.sort"
            v-model:sortOrder="sortOrder">
            <Column field="ticket_number" header="Ticket" sortable style="width: 120px">
                <template #body="{ data }">
                    <span class="font-mono font-medium">{{ data.ticket_number }}</span>
                </template>
            </Column>

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
                    <div class="font-medium">{{ data.subject }}</div>
                    <div class="text-sm text-surface-500">{{ data.type_label }}</div>
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

            <Column field="assigned_user_id" header="Assigned" sortable>
                <template #body="{ data }">
                    <span v-if="data.assigned_user_id" class="text-primary">
                        <i class="pi pi-user" />
                        {{ data.assigned_user_id }}
                    </span>
                    <span v-else class="text-surface-400">Unassigned</span>
                </template>
            </Column>

            <Column field="created_at" header="Created" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.created_at) }}
                </template>
            </Column>

            <Column header="Actions" style="width: 250px">
                <template #body="{ data }">
                    <div class="flex gap-1 flex-wrap">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewTicket(data)"
                            tooltip="View Details" />

                        <Button
                            v-if="canAssignTickets && !data.assigned_user_id && (data.status === 'open' || data.status === 'in_progress' || data.status === 'pending')"
                            icon="pi pi-user-plus" severity="primary" text rounded @click="assignToMe(data)"
                            tooltip="Assign to Me" />

                        <Button
                            v-if="canAssignTickets && data.assigned_user_id && (data.status === 'open' || data.status === 'in_progress' || data.status === 'pending')"
                            icon="pi pi-user-minus" severity="secondary" text rounded @click="unassignTicket(data)"
                            tooltip="Unassign" />

                        <Button v-if="canUpdateTickets && data.status === 'open'" icon="pi pi-play" severity="success"
                            text rounded @click="startTicket(data)" tooltip="Start" />

                        <Button
                            v-if="canUpdateTickets && (data.status === 'open' || data.status === 'in_progress' || data.status === 'pending')"
                            icon="pi pi-check" severity="success" text rounded @click="resolveTicket(data)"
                            tooltip="Resolve" />

                        <Button v-if="canUpdateTickets && (data.status === 'resolved' || data.status === 'closed')"
                            icon="pi pi-refresh" severity="warning" text rounded @click="reopenTicket(data)"
                            tooltip="Reopen" />

                        <Button v-if="canDeleteTickets" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmDelete(data)" tooltip="Delete" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create Ticket Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="New Ticket" :style="{ width: '600px' }" modal>
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
                    <label class="block text-sm font-medium mb-1">Subject *</label>
                    <InputText v-model="createForm.subject" class="w-full"
                        :class="{ 'p-invalid': getFieldError('subject') }"
                        placeholder="Brief description of the issue" />
                    <small v-if="getFieldError('subject')" class="text-red-500">
                        {{ getFieldError('subject') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <Textarea v-model="createForm.description" class="w-full" rows="3"
                        placeholder="Detailed description of the issue" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Priority</label>
                    <Select v-model="createForm.priority" :options="priorityOptions" optionLabel="label"
                        optionValue="value" class="w-full" placeholder="Select Priority" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Type</label>
                    <Select v-model="createForm.type" :options="typeOptions" optionLabel="label" optionValue="value"
                        class="w-full" placeholder="Select Type" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Conversation (Optional)</label>
                    <Select v-model="createForm.conversation_id" :options="conversations" optionLabel="subject"
                        optionValue="id" class="w-full" placeholder="Select Conversation" filter />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Assign To</label>
                    <Select v-model="createForm.assigned_user_id" :options="teamMembers" optionLabel="name"
                        optionValue="id" class="w-full" placeholder="Select Agent" filter />
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showCreateDialog = false" />
                <Button label="Create Ticket" icon="pi pi-plus" severity="primary" :loading="saving"
                    @click="handleCreate" />
            </template>
        </Dialog>

        <!-- Ticket Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" header="Ticket Details" :style="{ width: '800px' }" modal
            :maximizable="true">
            <div v-if="selectedTicket" class="space-y-4">
                <!-- Ticket Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-xl font-bold">{{ selectedTicket.ticket_number }}</h3>
                            <Tag :value="selectedTicket.status_label" :severity="selectedTicket.status_color" />
                            <Tag :value="selectedTicket.priority_label" :severity="selectedTicket.priority_color" />
                        </div>
                        <h4 class="text-lg font-medium mt-1">{{ selectedTicket.subject }}</h4>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="canAssignTickets && !selectedTicket.assigned_user_id && (selectedTicket.status === 'open' || selectedTicket.status === 'in_progress' || selectedTicket.status === 'pending')"
                            icon="pi pi-user-plus" label="Assign to Me" severity="primary" size="small"
                            @click="assignToMe(selectedTicket)" />
                        <Button v-if="canUpdateTickets && selectedTicket.status === 'open'" icon="pi pi-play"
                            label="Start" severity="success" size="small" @click="startTicket(selectedTicket)" />
                        <Button
                            v-if="canUpdateTickets && (selectedTicket.status === 'open' || selectedTicket.status === 'in_progress' || selectedTicket.status === 'pending')"
                            icon="pi pi-check" label="Resolve" severity="success" size="small"
                            @click="resolveTicket(selectedTicket)" />
                        <Button icon="pi pi-times" severity="secondary" text @click="showDetailsDialog = false" />
                    </div>
                </div>

                <Divider />

                <!-- Ticket Info -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-surface-500">Customer</label>
                        <div class="font-medium">{{ selectedTicket.customer?.full_name || 'Unknown' }}</div>
                        <div class="text-sm text-surface-500">{{ selectedTicket.customer?.email || 'No email' }}</div>
                        <div class="text-sm text-surface-500">{{ selectedTicket.customer?.phone || 'No phone' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Assigned To</label>
                        <div class="font-medium">{{ selectedTicket.assigned_user_id || 'Unassigned' }}</div>
                        <div class="text-sm text-surface-500">Type: {{ selectedTicket.type_label }}</div>
                        <div class="text-sm text-surface-500">Source: {{ selectedTicket.source_label }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Timeline</label>
                        <div class="text-sm">Created: {{ formatDate(selectedTicket.created_at) }}</div>
                        <div class="text-sm" v-if="selectedTicket.resolved_at">
                            Resolved: {{ formatDate(selectedTicket.resolved_at) }}
                        </div>
                        <div class="text-sm" v-if="selectedTicket.closed_at">
                            Closed: {{ formatDate(selectedTicket.closed_at) }}
                        </div>
                        <div class="text-sm text-danger" v-if="selectedTicket.is_overdue">
                            ⚠️ Overdue
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="selectedTicket.description">
                    <label class="text-sm text-surface-500">Description</label>
                    <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg mt-1 whitespace-pre-wrap">
                        {{ selectedTicket.description }}
                    </div>
                </div>

                <!-- Conversation Link -->
                <div v-if="selectedTicket.conversation">
                    <label class="text-sm text-surface-500">Related Conversation</label>
                    <div class="mt-1">
                        <Button icon="pi pi-comments" :label="'Conversation #' + selectedTicket.conversation.id"
                            severity="info" outlined size="small"
                            @click="viewConversation(selectedTicket.conversation.id)" />
                    </div>
                </div>

                <!-- Comments -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm text-surface-500">Comments</label>
                        <Button icon="pi pi-plus" label="Add Comment" severity="primary" size="small"
                            @click="showCommentDialog = true" />
                    </div>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        <div v-for="comment in comments" :key="comment.id" class="p-3 rounded-lg" :class="{
                            'bg-yellow-50 dark:bg-yellow-950': comment.is_internal,
                            'bg-surface-100 dark:bg-surface-800': !comment.is_internal,
                        }">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ comment.user_name }}</span>
                                    <Tag :value="comment.type_label"
                                        :severity="comment.is_internal ? 'warning' : 'info'" size="small" />
                                </div>
                                <span class="text-xs text-surface-400">{{ formatDate(comment.created_at) }}</span>
                            </div>
                            <div class="mt-1 text-sm whitespace-pre-wrap">{{ comment.content }}</div>
                        </div>
                        <div v-if="!comments.length" class="text-center text-surface-400 text-sm py-4">
                            No comments yet
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button v-if="selectedTicket && canUpdateTickets && selectedTicket.status !== 'closed'"
                    icon="pi pi-times" label="Close" severity="secondary" @click="closeTicket(selectedTicket)" />
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showDetailsDialog = false" />
            </template>
        </Dialog>

        <!-- Add Comment Dialog -->
        <Dialog v-model:visible="showCommentDialog" header="Add Comment" :style="{ width: '500px' }" modal>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Comment Type</label>
                    <Select v-model="commentForm.type" :options="commentTypeOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Content *</label>
                    <Textarea v-model="commentForm.content" class="w-full" rows="4"
                        placeholder="Write your comment..." />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showCommentDialog = false" />
                <Button label="Add Comment" icon="pi pi-plus" severity="primary" :loading="saving"
                    @click="handleAddComment" :disabled="!commentForm.content.trim()" />
            </template>
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