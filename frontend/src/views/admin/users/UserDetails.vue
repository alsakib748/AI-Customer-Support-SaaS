<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUsersStore } from '@/stores/users';
import { useAuthStore } from '@/stores/auth';
import userService from '@/services/userService';
import { toast } from 'vue3-toastify';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const usersStore = useUsersStore();

const id = computed(() => route.params.id);
const user = computed(() => usersStore.currentUser);

const activeTab = ref(0);

const can = (perm) => authStore.hasAnyPermission(perm);

// ----------------------------------------------------
// LOADING
// ----------------------------------------------------
const load = () => {
    usersStore.fetchUser(id.value).catch(() => {});
    fetchMemberships();
    fetchActivity();
};

onMounted(load);
watch(id, load);

const formatDate = (date, withTime = false) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {})
    });
};

// ----------------------------------------------------
// MEMBERSHIPS
// ----------------------------------------------------
const memberships = ref([]);
const membershipsLoading = ref(false);

const fetchMemberships = async () => {
    membershipsLoading.value = true;
    try {
        const res = await userService.getUserMemberships(id.value);
        memberships.value = res.data.data?.items || [];
    } catch (e) {
        // handled by interceptor
    } finally {
        membershipsLoading.value = false;
    }
};

// ----------------------------------------------------
// ACTIVITY
// ----------------------------------------------------
const activity = ref([]);
const activityLoading = ref(false);

const fetchActivity = async () => {
    activityLoading.value = true;
    try {
        const res = await userService.getUserActivity(id.value, 50);
        activity.value = res.data.data || [];
    } catch (e) {
        // handled by interceptor
    } finally {
        activityLoading.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 1 && activity.value.length === 0) fetchActivity();
});

// ----------------------------------------------------
// EDIT DIALOG
// ----------------------------------------------------
const showEditDialog = ref(false);
const saving = ref(false);
const editForm = reactive({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    phone: '',
    timezone: '',
    language: ''
});

const openEdit = () => {
    const u = user.value || {};
    editForm.first_name = u.first_name || '';
    editForm.last_name = u.last_name || '';
    editForm.username = u.username || '';
    editForm.email = u.email || '';
    editForm.phone = u.phone || '';
    editForm.timezone = u.timezone || '';
    editForm.language = u.language || '';
    showEditDialog.value = true;
};

const handleSave = async () => {
    saving.value = true;
    try {
        await usersStore.updateUser(id.value, { ...editForm });
        showEditDialog.value = false;
        toast.success('User updated.');
    } catch (e) {
        // handled by interceptor
    } finally {
        saving.value = false;
    }
};

// ----------------------------------------------------
// LIFECYCLE ACTIONS
// ----------------------------------------------------
const actionBusy = ref(false);
const showSuspendDialog = ref(false);
const suspendReason = ref('');
const showRevokeDialog = ref(false);
const revokeReason = ref('');

const handleActivate = async () => {
    actionBusy.value = true;
    try {
        await usersStore.activateUser(id.value);
        toast.success('User activated.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleSuspend = async () => {
    actionBusy.value = true;
    try {
        await usersStore.suspendUser(id.value, suspendReason.value);
        showSuspendDialog.value = false;
        suspendReason.value = '';
        toast.success('User suspended.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleRevoke = async () => {
    actionBusy.value = true;
    try {
        await usersStore.revokeUserSessions(id.value, revokeReason.value);
        showRevokeDialog.value = false;
        revokeReason.value = '';
        toast.success('All sessions revoked. The user must sign in again.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};
</script>

<template>
    <div class="p-6">
        <div v-if="!user" class="py-20 text-center text-surface-400">Loading user…</div>

        <div v-else>
            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Avatar :label="(user.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="h-16 w-16 bg-primary text-xl font-bold text-white" :image="user.avatar" />
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                            <Tag :value="user.status_label" :severity="user.status_color" />
                            <Tag v-if="user.scope" :value="user.scope" :severity="user.scope === 'platform' ? 'info' : 'secondary'" />
                            <Tag v-if="user.is_super_admin" value="Super Admin" severity="danger" icon="pi pi-shield" />
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-surface-500">
                            <span>{{ user.email }}</span>
                            <span v-if="user.username">@{{ user.username }}</span>
                            <span v-if="user.company_name">{{ user.company_name }}</span>
                        </div>
                        <div class="mt-1 text-sm text-surface-500">
                            <Tag v-if="user.email_verified" value="Verified" severity="success" class="mr-2" />
                            <Tag v-else value="Unverified" severity="secondary" class="mr-2" />
                            <span v-if="user.sessions_revoked_at">Sessions revoked {{ formatDate(user.sessions_revoked_at, true) }}</span>
                            <span v-if="user.must_change_password" class="ml-2 text-warning">Must change password at next login</span>
                        </div>
                        <div v-if="user.suspension_reason" class="mt-1 text-sm text-warning">Suspension reason: {{ user.suspension_reason }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined @click="router.push('/admin/users')" />

                    <div v-if="user.is_super_admin" class="flex items-center gap-2 rounded-md border border-danger bg-danger/10 px-3 py-1.5 text-sm text-danger">
                        <i class="pi pi-lock" />
                        <span>Protected — Super Admin accounts cannot be edited, deleted, suspended, or modified.</span>
                    </div>

                    <template v-else>
                        <Button v-if="can(['platform.users.update'])" icon="pi pi-pencil" label="Edit" severity="secondary" outlined @click="openEdit" />

                        <Button v-if="user.is_active && can(['platform.users.suspend'])" icon="pi pi-pause" label="Suspend" severity="warning" @click="showSuspendDialog = true" />

                        <Button v-if="!user.is_active && can(['platform.users.activate'])" icon="pi pi-play" label="Activate" severity="success" @click="handleActivate" />

                        <Button v-if="can(['platform.users.revoke_sessions'])" icon="pi pi-shield" label="Revoke Sessions" severity="danger" outlined @click="showRevokeDialog = true" />
                    </template>
                </div>
            </div>

            <!-- Tabs -->
            <TabView v-model:activeIndex="activeTab" class="mb-4">
                <TabPanel header="Overview">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-surface-200 bg-surface-0 p-5 shadow-sm">
                            <h3 class="mb-3 font-semibold">Identity</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Full name</dt>
                                    <dd>{{ user.name }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Username</dt>
                                    <dd>{{ user.username || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Email</dt>
                                    <dd>{{ user.email }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Phone</dt>
                                    <dd>{{ user.phone || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Company</dt>
                                    <dd>{{ user.company_name || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Timezone</dt>
                                    <dd>{{ user.timezone || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Language</dt>
                                    <dd>{{ user.language || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">UUID</dt>
                                    <dd class="font-mono text-xs">{{ user.uuid }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Roles</dt>
                                    <dd>{{ (user.roles || []).join(', ') || '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-lg border border-surface-200 bg-surface-0 p-5 shadow-sm">
                            <h3 class="mb-3 font-semibold">Security & Activity</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Last login</dt>
                                    <dd>{{ formatDate(user.last_login_at, true) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Last login IP</dt>
                                    <dd class="font-mono">{{ user.last_login_ip || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Current tenant</dt>
                                    <dd>#{{ user.current_tenant_id || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Tenant memberships</dt>
                                    <dd>{{ user.tenant_count }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Must change password</dt>
                                    <dd>{{ user.must_change_password ? 'Yes' : 'No' }}</dd>
                                </div>
                                <div v-if="user.sessions_revoked_at" class="flex justify-between">
                                    <dt class="text-surface-500">Sessions revoked</dt>
                                    <dd>{{ formatDate(user.sessions_revoked_at, true) }}</dd>
                                </div>
                                <div v-if="user.suspended_at" class="flex justify-between">
                                    <dt class="text-surface-500">Suspended at</dt>
                                    <dd>{{ formatDate(user.suspended_at, true) }}</dd>
                                </div>
                                <div v-if="user.deleted_at" class="flex justify-between">
                                    <dt class="text-surface-500">Deleted at</dt>
                                    <dd>{{ formatDate(user.deleted_at, true) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Created</dt>
                                    <dd>{{ formatDate(user.created_at, true) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Updated</dt>
                                    <dd>{{ formatDate(user.updated_at, true) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel header="Memberships">
                    <DataTable :value="memberships" :loading="membershipsLoading" class="w-full">
                        <template #empty>
                            <div class="py-12 text-center text-surface-400">This user belongs to no tenant workspaces.</div>
                        </template>
                        <Column header="Tenant" style="min-width: 200px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <Avatar :label="(data.tenant?.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="bg-primary text-white" />
                                    <div>
                                        <span class="font-medium">{{ data.tenant?.name || '#' + data.tenant_id }}</span>
                                        <Tag v-if="data.is_current" value="Current" severity="info" class="ml-2" />
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column field="role_label" header="Role" style="min-width: 140px">
                            <template #body="{ data }">
                                <Tag :value="data.role_label" :severity="data.is_owner ? 'warning' : 'secondary'" />
                            </template>
                        </Column>
                        <Column field="department" header="Department" />
                        <Column field="position" header="Position" />
                        <Column header="Joined">
                            <template #body="{ data }">{{ formatDate(data.joined_at) }}</template>
                        </Column>
                    </DataTable>
                </TabPanel>

                <TabPanel header="Activity">
                    <DataTable :value="activity" :loading="activityLoading" class="w-full">
                        <template #empty>
                            <div class="py-12 text-center text-surface-400">No activity recorded.</div>
                        </template>
                        <Column field="action_label" header="Action" style="min-width: 200px">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.action_label }}</span>
                                <div class="text-xs text-surface-500">{{ data.action }}</div>
                            </template>
                        </Column>
                        <Column field="actor_name" header="Actor">
                            <template #body="{ data }">{{ data.actor_name || '—' }}</template>
                        </Column>
                        <Column field="ip_address" header="IP" style="min-width: 120px">
                            <template #body="{ data }"
                                ><span class="font-mono text-xs">{{ data.ip_address || '—' }}</span></template
                            >
                        </Column>
                        <Column header="When" style="min-width: 170px">
                            <template #body="{ data }">{{ formatDate(data.created_at, true) }}</template>
                        </Column>
                    </DataTable>
                </TabPanel>
            </TabView>
        </div>

        <!-- Edit Dialog -->
        <Dialog v-model:visible="showEditDialog" header="Edit User" :style="{ width: '540px' }" modal>
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">First Name</label>
                        <InputText v-model="editForm.first_name" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Last Name</label>
                        <InputText v-model="editForm.last_name" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Email</label>
                    <InputText v-model="editForm.email" type="email" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Username</label>
                        <InputText v-model="editForm.username" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Phone</label>
                        <InputText v-model="editForm.phone" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Timezone</label>
                        <InputText v-model="editForm.timezone" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Language</label>
                        <InputText v-model="editForm.language" />
                    </div>
                </div>
                <p class="text-xs text-surface-500">Changing the email un-verifies the address and revokes existing sessions for security.</p>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showEditDialog = false" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="handleSave" />
            </template>
        </Dialog>

        <!-- Suspend Dialog -->
        <Dialog v-model:visible="showSuspendDialog" header="Suspend User" :style="{ width: '480px' }" modal>
            <p class="mb-3 text-sm text-surface-600">Suspending blocks this user from signing in anywhere on the platform. Data remains intact and this is reversible.</p>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">Reason (optional)</label>
                <Textarea v-model="suspendReason" rows="3" placeholder="e.g. Abuse, policy violation…" />
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showSuspendDialog = false" />
                <Button label="Suspend User" severity="warning" :loading="actionBusy" @click="handleSuspend" />
            </template>
        </Dialog>

        <!-- Revoke Sessions Dialog -->
        <Dialog v-model:visible="showRevokeDialog" header="Revoke Sessions" :style="{ width: '480px' }" modal>
            <p class="mb-3 text-sm text-surface-600">All existing tokens for this user become invalid immediately. This includes tokens on every device they are signed in on.</p>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">Reason (optional)</label>
                <Textarea v-model="revokeReason" rows="3" placeholder="e.g. Compromised device…" />
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showRevokeDialog = false" />
                <Button label="Revoke All Sessions" severity="danger" :loading="actionBusy" @click="handleRevoke" />
            </template>
        </Dialog>
    </div>
</template>
