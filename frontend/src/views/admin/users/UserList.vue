<script setup>
import { reactive, computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUsersStore } from '@/stores/users';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const router = useRouter();
const authStore = useAuthStore();
const usersStore = useUsersStore();

const loading = computed(() => usersStore.loading);
const users = computed(() => usersStore.users);
const total = computed(() => usersStore.totalUsers);
const stats = computed(() => usersStore.stats);

const filters = reactive({
    search: '',
    status: null,
    scope: null,
    verification: null,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20
});

const statusOptions = [
    { label: 'All Statuses', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Suspended', value: 'suspended' }
];

const scopeOptions = [
    { label: 'All Scopes', value: null },
    { label: 'Platform', value: 'platform', severity: 'info' },
    { label: 'Tenant', value: 'tenant', severity: 'secondary' }
];

const verificationOptions = [
    { label: 'All Verifications', value: null },
    { label: 'Verified', value: 'verified' },
    { label: 'Unverified', value: 'unverified' }
];

const scopeSeverity = (scope) => (scope === 'platform' ? 'info' : 'secondary');

const can = (perm) => authStore.hasAnyPermission(perm);

const load = () => usersStore.fetchUsers({ ...filters });

const debounceTimer = ref(null);
const onSearchInput = () => {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(load, 400);
};

const onFilterChange = () => load();
const onSortChange = (event) => {
    filters.sort = event.sortField || 'created_at';
    filters.direction = event.sortOrder === -1 ? 'desc' : 'asc';
    load();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    load();
};

const openUser = (user) => {
    router.push(`/admin/users/${user.id}`);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ----------------------------------------------------
// CREATE USER DIALOG
// ----------------------------------------------------
const showCreateDialog = ref(false);
const creating = ref(false);
const createForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    username: '',
    phone: '',
    timezone: '',
    language: ''
});

const openCreate = () => {
    createForm.first_name = '';
    createForm.last_name = '';
    createForm.email = '';
    createForm.username = '';
    createForm.phone = '';
    createForm.timezone = '';
    createForm.language = '';
    showCreateDialog.value = true;
};

const handleCreate = async () => {
    creating.value = true;
    try {
        await usersStore.createUser({ ...createForm });
        showCreateDialog.value = false;
        toast.success('User created. They must sign in with 11111111 and change their password.');
    } catch (e) {
        // handled by interceptor
    } finally {
        creating.value = false;
    }
};

onMounted(() => {
    load();
    usersStore.fetchStats().catch(() => {});
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Users</h1>
                <p class="text-surface-600">Manage platform identities (tenant membership is managed under Tenants)</p>
            </div>
            <Button v-if="can(['platform.users.create'])" label="Create User" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Summary cards -->
        <div class="mb-6 grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Total</p>
                <p class="mt-1 text-2xl font-bold">{{ stats?.total ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Active</p>
                <p class="mt-1 text-2xl font-bold text-success">{{ stats?.active ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Suspended</p>
                <p class="mt-1 text-2xl font-bold text-warning">{{ stats?.suspended ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Platform</p>
                <p class="mt-1 text-2xl font-bold text-info">{{ stats?.platform ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Tenant</p>
                <p class="mt-1 text-2xl font-bold">{{ stats?.tenant ?? 0 }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="filters.search" placeholder="Search name, email, username, phone" class="w-80" @input="onSearchInput" />
            </span>
            <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-44" @change="onFilterChange" />
            <Select v-model="filters.scope" :options="scopeOptions" optionLabel="label" optionValue="value" class="w-40" @change="onFilterChange" />
            <Select v-model="filters.verification" :options="verificationOptions" optionLabel="label" optionValue="value" class="w-44" @change="onFilterChange" />
        </div>

        <DataTable
            :value="users"
            :loading="loading"
            paginator
            :rows="filters.per_page"
            :totalRecords="total"
            :lazy="true"
            sortable
            :sortField="filters.sort"
            :sortOrder="filters.direction === 'desc' ? -1 : 1"
            :rowClass="(data) => (data.status === 'suspended' ? 'opacity-60' : '')"
            @sort="onSortChange"
            @page="onPageChange"
            class="w-full"
        >
            <template #empty>
                <div class="py-12 text-center text-surface-400">No users found.</div>
            </template>

            <Column header="User" style="min-width: 240px" :sortable="true" sortField="first_name">
                <template #body="{ data }">
                    <button class="flex items-center gap-3 text-left hover:text-primary" @click="openUser(data)">
                        <Avatar :label="(data.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="bg-primary text-white" :image="data.avatar" />
                        <div>
                            <div class="font-medium">{{ data.name }}</div>
                            <div class="text-xs text-surface-500">{{ data.email }}</div>
                        </div>
                    </button>
                </template>
            </Column>

            <Column header="Scope" style="min-width: 110px">
                <template #body="{ data }">
                    <Tag :value="data.scope" :severity="scopeSeverity(data.scope)" />
                </template>
            </Column>

            <Column header="Status" style="min-width: 120px">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>

            <Column header="Verification" style="min-width: 130px">
                <template #body="{ data }">
                    <Tag v-if="data.email_verified" value="Verified" severity="success" icon="pi pi-check-circle" />
                    <Tag v-else value="Unverified" severity="secondary" />
                </template>
            </Column>

            <Column field="tenant_count" header="Tenants" style="width: 110px">
                <template #body="{ data }">
                    <span class="font-medium">{{ data.tenant_count }} {{ data.tenant_count === 1 ? 'tenant' : 'tenants' }}</span>
                </template>
            </Column>

            <Column header="Last Login" style="min-width: 140px">
                <template #body="{ data }">{{ formatDate(data.last_login_at) }}</template>
            </Column>

            <Column header="Created" style="min-width: 130px">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>

            <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded title="View details" @click="openUser(data)" />
                        <Button
                            v-if="data.status === 'suspended' && !data.is_super_admin && can(['platform.users.activate'])"
                            icon="pi pi-play"
                            severity="success"
                            text
                            rounded
                            title="Activate"
                            @click="
                                usersStore
                                    .activateUser(data.id)
                                    .then(() => toast.success('User activated.'))
                                    .catch(() => {})
                            "
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create User Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="Create User" :style="{ width: '540px' }" modal>
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">First Name *</label>
                        <InputText v-model="createForm.first_name" placeholder="Jane" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Last Name *</label>
                        <InputText v-model="createForm.last_name" placeholder="Doe" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Email *</label>
                    <InputText v-model="createForm.email" type="email" placeholder="jane@acme.com" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Username</label>
                        <InputText v-model="createForm.username" placeholder="janedoe" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Phone</label>
                        <InputText v-model="createForm.phone" placeholder="+1 555 000 0000" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Timezone</label>
                        <InputText v-model="createForm.timezone" placeholder="UTC" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Language</label>
                        <InputText v-model="createForm.language" placeholder="en" />
                    </div>
                </div>
                <p class="text-xs text-surface-500">The account is created with the default password <b>11111111</b> and must be changed at first sign in. No verification email is sent.</p>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showCreateDialog = false" />
                <Button label="Create User" icon="pi pi-plus" :loading="creating" @click="handleCreate" />
            </template>
        </Dialog>
    </div>
</template>
