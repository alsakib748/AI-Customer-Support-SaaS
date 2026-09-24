<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTenantsStore } from '@/stores/tenants';
import { useAuthStore } from '@/stores/auth';
import tenantService from '@/services/tenantService';
import { toast } from 'vue3-toastify';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const tenantsStore = useTenantsStore();

const id = computed(() => route.params.id);
const tenant = computed(() => tenantsStore.currentTenant);

const activeTab = ref('overview');

// ----------------------------------------------------
// LOADING
// ----------------------------------------------------
const load = () => {
    tenantsStore.fetchTenant(id.value).catch(() => {});
    fetchMembers();
    fetchUsage();
};

onMounted(load);
watch(id, load);

// ----------------------------------------------------
// OVERVIEW
// ----------------------------------------------------
const overviewCards = computed(() => {
    const o = tenant.value?.overview || {};
    return [
        { label: 'Members', value: o.members ?? '—', icon: 'pi pi-users' },
        { label: 'Customers', value: o.customers ?? '—', icon: 'pi pi-user' },
        { label: 'Conversations', value: o.conversations ?? '—', icon: 'pi pi-comments' },
        { label: 'Tickets', value: o.tickets ?? '—', icon: 'pi pi-ticket' },
        { label: 'Messages', value: o.messages ?? '—', icon: 'pi pi-envelope' },
        { label: 'KB Articles', value: o.kb_articles ?? '—', icon: 'pi pi-book' },
        { label: 'AI Requests', value: o.ai_requests ?? '—', icon: 'pi pi-robot' },
        { label: 'AI Tokens', value: o.ai_tokens ?? '—', icon: 'pi pi-star' }
    ];
});

const formatDate = (date, withTime = false) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {})
    });
};

const can = (perm) => authStore.hasAnyPermission(perm);

// ----------------------------------------------------
// MEMBERS
// ----------------------------------------------------
const members = ref([]);
const membersLoading = ref(false);

const fetchMembers = async () => {
    membersLoading.value = true;
    try {
        const res = await tenantService.getTenantMembers(id.value);
        members.value = res.data.data?.items || [];
    } catch (e) {
        // handled by interceptor
    } finally {
        membersLoading.value = false;
    }
};

// ----------------------------------------------------
// USAGE
// ----------------------------------------------------
const usage = ref(null);
const usageLoading = ref(false);

const fetchUsage = async () => {
    usageLoading.value = true;
    try {
        const res = await tenantService.getTenantUsage(id.value);
        usage.value = res.data.data;
    } catch (e) {
        // handled by interceptor
    } finally {
        usageLoading.value = false;
    }
};

const usageBlocks = computed(() => {
    const blocks = usage.value?.blocks || {};
    const meta = {
        ai_requests: { label: 'AI Requests', icon: 'pi pi-robot' },
        ai_tokens: { label: 'AI Tokens', icon: 'pi pi-star' },
        agents: { label: 'Agents', icon: 'pi pi-users' },
        customers: { label: 'Customers', icon: 'pi pi-user' },
        widgets: { label: 'Widgets', icon: 'pi pi-megaphone' },
        documents: { label: 'Documents', icon: 'pi pi-file' },
        kb_articles: { label: 'KB Articles', icon: 'pi pi-book' },
        conversations: { label: 'Conversations', icon: 'pi pi-comments' }
    };
    return Object.entries(meta).map(([key, m]) => ({
        ...m,
        key,
        data: blocks[key]
    }));
});

// ----------------------------------------------------
// ACTIVITY
// ----------------------------------------------------
const activity = ref([]);
const activityLoading = ref(false);

const fetchActivity = async () => {
    activityLoading.value = true;
    try {
        const res = await tenantService.getTenantActivity(id.value, 50);
        activity.value = res.data.data || [];
    } catch (e) {
        // handled by interceptor
    } finally {
        activityLoading.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 'activity' && activity.value.length === 0) fetchActivity();
});

// ----------------------------------------------------
// LIFECYCLE ACTIONS
// ----------------------------------------------------
const actionBusy = ref(false);
const showSuspendDialog = ref(false);
const suspendReason = ref('');
const showArchiveDialog = ref(false);
const archiveConfirmation = ref('');
const showTransferDialog = ref(false);

const afterAction = (message) => {
    load();
    toast.success(message);
};

const handleActivate = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.activateTenant(id.value);
        afterAction('Tenant activated.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleSuspend = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.suspendTenant(id.value, suspendReason.value);
        showSuspendDialog.value = false;
        suspendReason.value = '';
        afterAction('Tenant suspended.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleArchive = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.archiveTenant(id.value, archiveConfirmation.value);
        showArchiveDialog.value = false;
        archiveConfirmation.value = '';
        afterAction('Tenant archived.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleRestore = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.restoreTenant(id.value);
        afterAction('Tenant restored.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleRetryProvisioning = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.retryProvisioning(id.value);
        afterAction('Provisioning retried.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

const handleManage = async () => {
    actionBusy.value = true;
    try {
        await tenantsStore.manageTenant(id.value);
        toast.success(`Now managing "${tenant.value?.name}". Use the switch API in the tenant app, then Exit Context when done.`);
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};

// Transfer owner
const selectedNewOwner = ref(null);
const openTransfer = () => {
    selectedNewOwner.value = null;
    showTransferDialog.value = true;
};

const handleTransfer = async () => {
    if (!selectedNewOwner.value) return;
    actionBusy.value = true;
    try {
        await tenantsStore.transferOwner(id.value, selectedNewOwner.value);
        showTransferDialog.value = false;
        afterAction('Ownership transferred.');
    } catch (e) {
        // handled by interceptor
    } finally {
        actionBusy.value = false;
    }
};
</script>

<template>
    <div class="p-6">
        <div v-if="!tenant" class="py-20 text-center text-surface-400">Loading tenant…</div>

        <div v-else>
            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Avatar :label="(tenant.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="h-16 w-16 bg-primary text-xl font-bold text-white" />
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold">{{ tenant.name }}</h1>
                            <Tag :value="tenant.status_label" :severity="tenant.status_color" />
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-surface-500">
                            <span v-if="tenant.slug">@{{ tenant.slug }}</span>
                            <span v-if="tenant.subdomain">{{ tenant.subdomain }}</span>
                            <span v-if="tenant.support_email">{{ tenant.support_email }}</span>
                        </div>
                        <div v-if="tenant.suspension_reason" class="mt-1 text-sm text-warning">Suspension reason: {{ tenant.suspension_reason }}</div>
                        <div v-if="tenant.provisioning_error" class="mt-1 text-sm text-danger">Provisioning error: {{ tenant.provisioning_error }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button icon="pi pi-arrow-left" label="Back" severity="secondary" outlined @click="router.push('/admin/tenants')" />

                    <Button v-if="can(['platform.tenants.manage']) && !tenant.is_archived" icon="pi pi-eye-slash" label="Manage Tenant" @click="handleManage" />

                    <Button v-if="['active', 'trial'].includes(tenant.status) && can(['platform.tenants.suspend'])" icon="pi pi-pause" label="Suspend" severity="warning" @click="showSuspendDialog = true" />

                    <Button v-if="['suspended', 'provisioning_failed'].includes(tenant.status) && can(['platform.tenants.activate'])" icon="pi pi-play" label="Activate" severity="success" @click="handleActivate" />

                    <Button v-if="tenant.status === 'provisioning_failed' && can(['platform.tenants.activate'])" icon="pi pi-refresh" label="Retry Provisioning" severity="secondary" @click="handleRetryProvisioning" />

                    <Button v-if="['suspended', 'archived'].includes(tenant.status) && can(['platform.tenants.restore'])" icon="pi pi-history" label="Restore" @click="handleRestore" />

                    <Button v-if="!tenant.is_archived && can(['platform.tenants.archive'])" icon="pi pi-inbox" label="Archive" severity="danger" outlined @click="showArchiveDialog = true" />
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-3">
                <Button v-if="can(['platform.tenants.transfer_owner']) && !tenant.is_archived" icon="pi pi-user-edit" label="Transfer Ownership" severity="secondary" outlined @click="openTransfer" />
            </div>

            <!-- Tabs -->
            <TabView v-model:activeIndex="activeTab" class="mb-4">
                <TabPanel header="Overview">
                    <div class="mb-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="card in overviewCards" :key="card.label" class="flex items-center gap-4 rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-100">
                                <i :class="card.icon" class="text-primary" />
                            </div>
                            <div>
                                <p class="text-sm text-surface-500">{{ card.label }}</p>
                                <p class="text-xl font-bold">{{ card.value }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-surface-200 bg-surface-0 p-5 shadow-sm">
                            <h3 class="mb-3 font-semibold">Tenant Info</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Industry</dt>
                                    <dd>{{ tenant.industry || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Timezone</dt>
                                    <dd>{{ tenant.timezone || '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Created</dt>
                                    <dd>{{ formatDate(tenant.created_at) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Provisioned</dt>
                                    <dd>{{ formatDate(tenant.provisioned_at) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-500">Trial ends</dt>
                                    <dd>{{ formatDate(tenant.trial_ends_at) }}</dd>
                                </div>
                                <div v-if="tenant.suspended_at" class="flex justify-between">
                                    <dt class="text-surface-500">Suspended</dt>
                                    <dd>{{ formatDate(tenant.suspended_at) }}</dd>
                                </div>
                                <div v-if="tenant.archived_at" class="flex justify-between">
                                    <dt class="text-surface-500">Archived</dt>
                                    <dd>{{ formatDate(tenant.archived_at) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-lg border border-surface-200 bg-surface-0 p-5 shadow-sm">
                            <h3 class="mb-3 font-semibold">Subscription</h3>
                            <template v-if="tenant.subscription">
                                <div class="mb-3 flex items-center gap-3">
                                    <div>
                                        <div class="font-semibold">{{ tenant.subscription.plan?.name || '—' }}</div>
                                        <div class="text-sm text-surface-500">
                                            {{ tenant.subscription.billing_cycle }} ·
                                            {{ tenant.subscription.current_period_ends_at ? 'ends ' + formatDate(tenant.subscription.current_period_ends_at) : 'no period' }}
                                        </div>
                                    </div>
                                    <Tag :value="tenant.subscription.status_label" :severity="tenant.subscription.status_color" />
                                </div>
                                <div v-if="usage?.plan" class="text-sm text-surface-500">Limits from {{ usage.plan.name }} ({{ usage.billing_cycle }})</div>
                            </template>
                            <p v-else class="text-sm text-surface-400">No active subscription.</p>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel header="Usage">
                    <div v-if="usageLoading" class="py-16 text-center text-surface-400">Loading usage…</div>
                    <div v-else-if="usage" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div v-for="block in usageBlocks" :key="block.key" class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-sm font-medium">
                                    <i :class="block.icon" class="text-primary" />
                                    {{ block.label }}
                                </div>
                                <div v-if="block.data" class="text-xs text-surface-500">
                                    {{ block.data.used }} / <span v-if="!block.data.unlimited">{{ block.data.limit }}</span>
                                    <span v-else>∞</span>
                                </div>
                            </div>
                            <ProgressBar v-if="block.data" :value="Math.min(block.data.percentage, 100)" :class="block.data.percentage > 90 ? 'p-danger' : ''" :style="{ height: '8px' }" />
                        </div>
                    </div>
                    <p v-else class="py-12 text-center text-surface-400">No usage data available.</p>
                </TabPanel>

                <TabPanel header="Members">
                    <DataTable :value="members" :loading="membersLoading" class="w-full">
                        <Column header="Member" style="min-width: 200px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <Avatar :label="(data.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="bg-primary text-white" />
                                    <div>
                                        <div class="font-medium">{{ data.name }}</div>
                                        <div class="text-xs text-surface-500">{{ data.email }}</div>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column field="role_label" header="Role" style="min-width: 140px">
                            <template #body="{ data }">
                                <Tag :value="data.role_label" :severity="data.is_owner ? 'warning' : data.role === 'admin' ? 'info' : 'secondary'" />
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
                        <Column field="action_label" header="Action" style="min-width: 200px">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.action_label }}</span>
                                <div class="text-xs text-surface-500">{{ data.action }}</div>
                            </template>
                        </Column>
                        <Column field="user_name" header="User" />
                        <Column header="When" style="min-width: 170px">
                            <template #body="{ data }">{{ formatDate(data.created_at, true) }}</template>
                        </Column>
                    </DataTable>
                </TabPanel>
            </TabView>
        </div>

        <!-- Suspend Dialog -->
        <Dialog v-model:visible="showSuspendDialog" header="Suspend Tenant" :style="{ width: '480px' }" modal>
            <p class="mb-3 text-sm text-surface-600">Suspending blocks all tenant access. Data remains intact and this is reversible.</p>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">Reason (optional)</label>
                <Textarea v-model="suspendReason" rows="3" placeholder="e.g. Non-payment, abuse…" />
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showSuspendDialog = false" />
                <Button label="Suspend Tenant" severity="warning" :loading="actionBusy" @click="handleSuspend" />
            </template>
        </Dialog>

        <!-- Archive Dialog -->
        <Dialog v-model:visible="showArchiveDialog" header="Archive Tenant" :style="{ width: '480px' }" modal>
            <p class="mb-3 text-sm text-surface-600">Archiving is long-term and irreversible without a restore. Data is retained but normal access is removed. Type <b>ARCHIVE</b> to confirm.</p>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">Confirmation *</label>
                <InputText v-model="archiveConfirmation" placeholder="ARCHIVE" />
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showArchiveDialog = false" />
                <Button label="Archive Tenant" severity="danger" :disabled="archiveConfirmation !== 'ARCHIVE'" :loading="actionBusy" @click="handleArchive" />
            </template>
        </Dialog>

        <!-- Transfer Ownership Dialog -->
        <Dialog v-model:visible="showTransferDialog" header="Transfer Ownership" :style="{ width: '480px' }" modal>
            <p class="mb-3 text-sm text-surface-600">Choose an existing member to become the new owner. The current owner becomes an admin.</p>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium">New Owner</label>
                <Select v-model="selectedNewOwner" :options="members.filter((m) => !m.is_owner)" optionLabel="email" optionValue="user_id" placeholder="Select a member…" class="w-full" />
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showTransferDialog = false" />
                <Button label="Transfer" :disabled="!selectedNewOwner" :loading="actionBusy" @click="handleTransfer" />
            </template>
        </Dialog>
    </div>
</template>
