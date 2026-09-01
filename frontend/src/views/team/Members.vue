<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useTeamStore } from '@/stores/team';
// import { useToast } from 'primevue/usetoast';

const teamStore = useTeamStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showMemberDialog = ref(false);
const showEditDialog = ref(false);
const showInviteDialog = ref(false);
const selectedMember = ref(null);
const skillInput = ref('');
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    department: null,
    availability_status: null,
    role: null,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20,
});

const editForm = reactive({
    id: null,
    name: '',
    email: '',
    department: '',
    position: '',
    availability_status: 'online',
    max_concurrent_chats: 5,
    skills: [],
});

const inviteForm = reactive({
    email: '',
    role: 'agent',
    department: '',
    position: '',
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => teamStore.loading);
const saving = computed(() => teamStore.saving);
const deleting = computed(() => teamStore.deleting);
const members = computed(() => teamStore.members);
const totalMembers = computed(() => teamStore.totalMembers);
const statistics = computed(() => teamStore.statistics);
const departments = computed(() => teamStore.departments);
const canManageTeam = computed(() => teamStore.canManageTeam);

const statusOptions = [
    { label: 'Online', value: 'online' },
    { label: 'Offline', value: 'offline' },
    { label: 'Away', value: 'away' },
    { label: 'Busy', value: 'busy' },
];

const roleOptions = [
    { label: 'Administrator', value: 'admin' },
    { label: 'Manager', value: 'manager' },
    { label: 'Support Agent', value: 'agent' },
    { label: 'Viewer', value: 'viewer' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    await Promise.all([
        teamStore.fetchMembers({ ...filters }),
        teamStore.fetchStatistics(),
        teamStore.fetchDepartments(),
    ]);
};

const refreshData = () => {
    loadData();
};

const applyFilters = () => {
    teamStore.filters = { ...filters };
    teamStore.fetchMembers();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        department: null,
        availability_status: null,
        role: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    teamStore.fetchMembers({ ...filters });
};

const onSortChange = (event) => {
    filters.sort = event.sortField;
    filters.direction = event.sortOrder === 1 ? 'asc' : 'desc';
    applyFilters();
};

const viewMember = (member) => {
    selectedMember.value = member;
    showMemberDialog.value = true;
};

const editMember = (member) => {
    editForm.id = member.id;
    editForm.name = member.name;
    editForm.email = member.email;
    editForm.department = member.department || '';
    editForm.position = member.position || '';
    editForm.availability_status = member.availability_status;
    editForm.max_concurrent_chats = member.max_concurrent_chats;
    editForm.skills = [...(member.skills || [])];

    showMemberDialog.value = false;
    showEditDialog.value = true;
};

const handleUpdateMember = async () => {
    try {
        await teamStore.updateMember(editForm.id, {
            department: editForm.department,
            position: editForm.position,
            availability_status: editForm.availability_status,
            max_concurrent_chats: editForm.max_concurrent_chats,
            skills: editForm.skills,
        });

        showEditDialog.value = false;
        await loadData();

    } catch (error) {
        // Error handled in store
    }
};

const confirmRemove = (member) => {
    if (confirm(`Are you sure you want to remove ${member.name} from the team?`)) {
        handleRemoveMember(member.id);
    }
};

const handleRemoveMember = async (id) => {
    try {
        await teamStore.removeMember(id);
        await loadData();

    } catch (error) {
        // Error handled in store
    }
};

const addSkill = () => {
    const skill = skillInput.value.trim();
    if (skill && !editForm.skills.includes(skill)) {
        editForm.skills.push(skill);
        skillInput.value = '';
    }
};

const removeSkill = (skill) => {
    editForm.skills = editForm.skills.filter(s => s !== skill);
};

const handleSendInvitation = async () => {
    try {
        await teamStore.sendInvitation(inviteForm);

        showInviteDialog.value = false;
        resetInviteForm();
        await teamStore.fetchInvitations();

    } catch (error) {
        // Error handled in store
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

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
    // console.log('Fetch Statistics: ', teamStore.fetchStatistics());
    // console.log('Fetch Departments: ', teamStore.fetchDepartments());
});

// Watch for search filter changes
watch(() => filters.search, () => {
    applyFilters();
});
</script>

<!-- src/views/team/Members.vue -->
<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Team Members</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage your team members and their permissions</p>
            </div>
            <div class="flex gap-3">
                <Button label="Invite Member" icon="pi pi-user-plus" severity="primary" @click="showInviteDialog = true"
                    v-if="canManageTeam" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalMembers }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Members</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ onlineMembers }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Online</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ statistics?.away || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Away</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-500">{{ statistics?.offline || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Offline</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="filters.search" placeholder="Search members..." class="w-full"
                    @input="applyFilters" />
            </div>
            <div class="w-48">
                <Select v-model="filters.department" :options="departments" placeholder="Department" class="w-full"
                    @change="applyFilters" clearable />
            </div>
            <div class="w-48">
                <Select v-model="filters.availability_status" :options="statusOptions" optionLabel="label"
                    optionValue="value" placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-48">
                <Select v-model="filters.role" :options="roleOptions" optionLabel="label" optionValue="value"
                    placeholder="Role" class="w-full" @change="applyFilters" clearable />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <!-- Members Table -->
        <DataTable :value="members" :loading="loading" paginator :rows="filters.per_page" :totalRecords="totalMembers"
            :lazy="true" @page="onPageChange" @sort="onSortChange" class="w-full" v-model:sortField="filters.sort"
            v-model:sortOrder="sortOrder">
            <Column field="name" header="Name" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-3">
                        <Avatar :label="data.name?.charAt(0) || '?'" :image="data.avatar_url" shape="circle"
                            size="large" />
                        <div>
                            <div class="font-medium">{{ data.name }}</div>
                            <div class="text-sm text-surface-500">{{ data.email }}</div>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="department" header="Department" sortable>
                <template #body="{ data }">
                    <span class="px-2 py-1 bg-surface-100 dark:bg-surface-800 rounded-md text-sm">
                        {{ data.department || '—' }}
                    </span>
                </template>
            </Column>

            <Column field="position" header="Position" sortable>
                <template #body="{ data }">
                    {{ data.position || '—' }}
                </template>
            </Column>

            <Column field="role_label" header="Role" sortable>
                <template #body="{ data }">
                    <Tag :value="data.role_label" :severity="data.is_owner ? 'warning' : 'info'" />
                </template>
            </Column>

            <Column field="availability_status" header="Status" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" :class="{
                            'bg-green-500': data.availability_status === 'online',
                            'bg-yellow-500': data.availability_status === 'away',
                            'bg-red-500': data.availability_status === 'busy',
                            'bg-gray-500': data.availability_status === 'offline',
                        }" />
                        <span>{{ data.availability_status_label }}</span>
                    </div>
                </template>
            </Column>

            <Column field="max_concurrent_chats" header="Max Chats" sortable>
                <template #body="{ data }">
                    {{ data.max_concurrent_chats }}
                </template>
            </Column>

            <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewMember(data)"
                            tooltip="View Details" />
                        <Button v-if="canManageTeam && !data.is_owner" icon="pi pi-pencil" severity="warning" text
                            rounded @click="editMember(data)" tooltip="Edit" />
                        <Button v-if="canManageTeam && !data.is_owner" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmRemove(data)" tooltip="Remove" :loading="deleting" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Member Details Dialog -->
        <Dialog v-model:visible="showMemberDialog" header="Member Details" :style="{ width: '600px' }" modal>
            <div v-if="selectedMember" class="space-y-4">
                <div class="flex items-center gap-4">
                    <Avatar :label="selectedMember.name?.charAt(0) || '?'" :image="selectedMember.avatar_url"
                        shape="circle" size="xlarge" />
                    <div>
                        <div class="text-xl font-bold">{{ selectedMember.name }}</div>
                        <div class="text-surface-500">{{ selectedMember.email }}</div>
                    </div>
                </div>

                <Divider />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-surface-500">Department</label>
                        <div class="font-medium">{{ selectedMember.department || '—' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Position</label>
                        <div class="font-medium">{{ selectedMember.position || '—' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Role</label>
                        <div class="font-medium">
                            <Tag :value="selectedMember.role_label"
                                :severity="selectedMember.is_owner ? 'warning' : 'info'" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Status</label>
                        <div class="font-medium flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :class="{
                                'bg-green-500': selectedMember.availability_status === 'online',
                                'bg-yellow-500': selectedMember.availability_status === 'away',
                                'bg-red-500': selectedMember.availability_status === 'busy',
                                'bg-gray-500': selectedMember.availability_status === 'offline',
                            }" />
                            {{ selectedMember.availability_status_label }}
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Max Concurrent Chats</label>
                        <div class="font-medium">{{ selectedMember.max_concurrent_chats }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Joined</label>
                        <div class="font-medium">{{ formatDate(selectedMember.created_at) }}</div>
                    </div>
                </div>

                <div v-if="selectedMember.skills?.length">
                    <label class="text-sm text-surface-500">Skills</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <Tag v-for="skill in selectedMember.skills" :key="skill" :value="skill" severity="info" />
                    </div>
                </div>

                <div v-if="selectedMember.permissions?.length">
                    <label class="text-sm text-surface-500">Permissions</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <Tag v-for="perm in selectedMember.permissions" :key="perm" :value="perm"
                            severity="secondary" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showMemberDialog = false" />
                <Button v-if="canManageTeam && selectedMember && !selectedMember.is_owner" label="Edit"
                    icon="pi pi-pencil" severity="warning" @click="editMember(selectedMember)" />
            </template>
        </Dialog>

        <!-- Edit Member Dialog -->
        <Dialog v-model:visible="showEditDialog" header="Edit Member" :style="{ width: '500px' }" modal>
            <form @submit.prevent="handleUpdateMember" class="space-y-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Member</label>
                    <div class="flex items-center gap-3 p-2 bg-surface-100 dark:bg-surface-800 rounded-lg">
                        <Avatar :label="editForm.name?.charAt(0) || '?'" shape="circle" />
                        <div>
                            <div class="font-medium">{{ editForm.name }}</div>
                            <div class="text-sm text-surface-500">{{ editForm.email }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Department</label>
                    <InputText v-model="editForm.department" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Position</label>
                    <InputText v-model="editForm.position" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Availability Status</label>
                    <Select v-model="editForm.availability_status" :options="statusOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Max Concurrent Chats</label>
                    <InputNumber v-model="editForm.max_concurrent_chats" :min="1" :max="50" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Skills</label>
                    <div class="flex gap-2 mb-2">
                        <InputText v-model="skillInput" placeholder="Add a skill" class="flex-1"
                            @keydown.enter.prevent="addSkill" />
                        <Button icon="pi pi-plus" severity="secondary" @click="addSkill" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Chip v-for="skill in editForm.skills" :key="skill" :label="skill" removable
                            @remove="removeSkill(skill)" />
                    </div>
                </div>

                <div v-if="getError('general')" class="text-red-500 text-sm">
                    {{ getError('general') }}
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showEditDialog = false" />
                <Button label="Save" icon="pi pi-save" severity="primary" :loading="saving"
                    @click="handleUpdateMember" />
            </template>
        </Dialog>

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

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--surface-ground);
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}
</style>
