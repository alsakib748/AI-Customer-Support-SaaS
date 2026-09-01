<!-- src/views/team/Invitations.vue -->

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useTeamStore } from '@/stores/team';
import { useToast } from 'primevue/usetoast';

const teamStore = useTeamStore();
const toast = useToast();

// State
const showInviteDialog = ref(false);
const saving = ref(false);

const invitationFilters = reactive({
    search: '',
    status: 'pending',
    per_page: 20,
});

const inviteForm = reactive({
    email: '',
    role: 'agent',
    department: '',
    position: '',
});

// Computed
const loading = computed(() => teamStore.loading);
const invitations = computed(() => teamStore.invitations);
const totalInvitations = computed(() => teamStore.pagination?.total || 0);
const canManageTeam = computed(() => teamStore.canManageTeam);

const statusFilterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Expired', value: 'expired' },
    { label: 'Revoked', value: 'revoked' },
];

const roleOptions = [
    { label: 'Administrator', value: 'admin' },
    { label: 'Manager', value: 'manager' },
    { label: 'Support Agent', value: 'agent' },
    { label: 'Viewer', value: 'viewer' },
];

// Methods
const loadData = async () => {
    await teamStore.fetchInvitations({ ...invitationFilters });
};

const refreshData = () => {
    loadData();
};

const applyInvitationFilters = () => {
    teamStore.invitationFilters = { ...invitationFilters };
    teamStore.fetchInvitations();
};

const clearInvitationFilters = () => {
    Object.assign(invitationFilters, {
        search: '',
        status: 'pending',
        per_page: 20,
    });
    applyInvitationFilters();
};

const onInvitationPageChange = (event) => {
    invitationFilters.per_page = event.rows;
    teamStore.fetchInvitations({ ...invitationFilters });
};

const handleSendInvitation = async () => {
    saving.value = true;
    try {
        await teamStore.sendInvitation(inviteForm);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Invitation sent successfully 📧',
            life: 3000,
        });

        showInviteDialog.value = false;
        resetInviteForm();
        await loadData();

    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to send invitation',
            life: 5000,
        });
    } finally {
        saving.value = false;
    }
};

const resendInvitation = async (id) => {
    try {
        await teamStore.resendInvitation(id);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Invitation resent successfully 📧',
            life: 3000,
        });

        await loadData();

    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to resend invitation',
            life: 5000,
        });
    }
};

const revokeInvitation = async (id) => {
    if (!confirm('Are you sure you want to revoke this invitation?')) return;

    try {
        await teamStore.revokeInvitation(id);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Invitation revoked successfully',
            life: 3000,
        });

        await loadData();

    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to revoke invitation',
            life: 5000,
        });
    }
};

const resetInviteForm = () => {
    Object.assign(inviteForm, {
        email: '',
        role: 'agent',
        department: '',
        position: '',
    });
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getError = (field) => {
    return teamStore.getFieldError(field);
};

// Lifecycle
onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-surface-900 dark:text-surface-0">Pending Invitations</h2>
                <p class="text-surface-600 dark:text-surface-400">Manage team member invitations</p>
            </div>
            <div class="flex gap-3">
                <Button label="New Invitation" icon="pi pi-plus" severity="primary" @click="showInviteDialog = true"
                    v-if="canManageTeam" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="invitationFilters.search" placeholder="Search invitations..." class="w-full"
                    @input="applyInvitationFilters" />
            </div>
            <div class="w-48">
                <Select v-model="invitationFilters.status" :options="statusFilterOptions" optionLabel="label"
                    optionValue="value" placeholder="Status" class="w-full" @change="applyInvitationFilters" />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearInvitationFilters" />
        </div>

        <!-- Invitations Table -->
        <DataTable :value="invitations" :loading="loading" paginator :rows="invitationFilters.per_page"
            :totalRecords="totalInvitations" :lazy="true" @page="onInvitationPageChange" class="w-full">
            <Column field="email" header="Email">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.email }}</div>
                        <div class="text-sm text-surface-500">Invited by {{ data.invited_by?.name || 'Unknown' }}</div>
                    </div>
                </template>
            </Column>

            <Column field="role" header="Role">
                <template #body="{ data }">
                    <Tag :value="data.role" />
                </template>
            </Column>

            <Column field="department" header="Department">
                <template #body="{ data }">
                    {{ data.department || '—' }}
                </template>
            </Column>

            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>

            <Column field="expires_at" header="Expires">
                <template #body="{ data }">
                    {{ formatDate(data.expires_at) }}
                </template>
            </Column>

            <Column header="Actions" style="width: 150px">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button v-if="data.is_pending" icon="pi pi-send" severity="info" text rounded
                            @click="resendInvitation(data.id)" tooltip="Resend" :loading="saving" />
                        <Button v-if="data.is_pending" icon="pi pi-times" severity="danger" text rounded
                            @click="revokeInvitation(data.id)" tooltip="Revoke" :loading="saving" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Invite Member Dialog -->
        <Dialog v-model:visible="showInviteDialog" header="Invite Member" :style="{ width: '500px' }" modal>
            <form @submit.prevent="handleSendInvitation" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Email Address *</label>
                    <InputText v-model="inviteForm.email" type="email" class="w-full"
                        :class="{ 'p-invalid': getError('email') }" placeholder="member@example.com" />
                    <small v-if="getError('email')" class="text-red-500">
                        {{ getError('email') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Role *</label>
                    <Select v-model="inviteForm.role" :options="roleOptions" optionLabel="label" optionValue="value"
                        class="w-full" :class="{ 'p-invalid': getError('role') }" placeholder="Select Role" />
                    <small v-if="getError('role')" class="text-red-500">
                        {{ getError('role') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Department</label>
                    <InputText v-model="inviteForm.department" class="w-full" placeholder="e.g., Customer Support" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Position</label>
                    <InputText v-model="inviteForm.position" class="w-full" placeholder="e.g., Support Agent" />
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showInviteDialog = false" />
                <Button label="Send Invitation" icon="pi pi-send" severity="primary" :loading="saving"
                    @click="handleSendInvitation" />
            </template>
        </Dialog>

        <!-- Toast -->
        <Toast />
    </div>
</template>
