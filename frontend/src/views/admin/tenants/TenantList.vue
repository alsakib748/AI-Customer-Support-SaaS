<script setup>
import { reactive, computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useTenantsStore } from '@/stores/tenants';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const router = useRouter();
const authStore = useAuthStore();
const tenantsStore = useTenantsStore();

const loading = computed(() => tenantsStore.loading);
const tenants = computed(() => tenantsStore.tenants);
const total = computed(() => tenantsStore.totalTenants);
const plans = computed(() => tenantsStore.plans);

const filters = reactive({
    search: '',
    status: null,
    subscription_status: null,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20
});

const statusOptions = [
    { label: 'All Statuses', value: null },
    { label: 'Trial', value: 'trial' },
    { label: 'Active', value: 'active' },
    { label: 'Suspended', value: 'suspended' },
    { label: 'Archived', value: 'archived' },
    { label: 'Provisioning', value: 'provisioning' },
    { label: 'Provisioning Failed', value: 'provisioning_failed' }
];

const subscriptionStatusOptions = [
    { label: 'All Subscription Statuses', value: null },
    { label: 'Trialing', value: 'trialing' },
    { label: 'Active', value: 'active' },
    { label: 'Past Due', value: 'past_due' },
    { label: 'Cancelled', value: 'cancelled' },
    { label: 'Expired', value: 'expired' }
];

const summary = computed(() => {
    const all = tenants.value;
    return {
        total: total.value,
        trial: all.filter((t) => t.status === 'trial').length,
        active: all.filter((t) => t.status === 'active').length,
        suspended: all.filter((t) => t.status === 'suspended').length,
        provisioningFailed: all.filter((t) => t.status === 'provisioning_failed').length
    };
});

const can = (perm) => authStore.hasAnyPermission(perm);

const load = () => tenantsStore.fetchTenants({ ...filters });

const debounceTimer = ref(null);
const onSearchInput = () => {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(load, 400);
};

const onStatusChange = () => load();
const onSubscriptionStatusChange = () => load();

const onPageChange = (event) => {
    filters.per_page = event.rows;
    load();
};

const openTenant = (tenant) => {
    router.push(`/admin/tenants/${tenant.id}`);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

// ----------------------------------------------------
// CREATE TENANT DIALOG
// ----------------------------------------------------
const showCreateDialog = ref(false);
const creating = ref(false);
const createForm = reactive({
    name: '',
    owner: { name: '', email: '', password: '' },
    subdomain: '',
    support_email: '',
    plan_id: null
});

const openCreate = () => {
    createForm.name = '';
    createForm.owner = { name: '', email: '', password: '' };
    createForm.subdomain = '';
    createForm.support_email = '';
    createForm.plan_id = null;
    if (plans.value.length === 0) {
        tenantsStore.fetchPlans().catch(() => {});
    }
    showCreateDialog.value = true;
};

const handleCreate = async () => {
    creating.value = true;
    try {
        await tenantsStore.createTenant({ ...createForm });
        showCreateDialog.value = false;
        toast.success('Tenant created and provisioned.');
    } catch (e) {
        // error toast handled by api interceptor
    } finally {
        creating.value = false;
    }
};

const manageTenant = async (tenant, event) => {
    event.stopPropagation();
    try {
        await tenantsStore.manageTenant(tenant.id);
        toast.success(`Now managing "${tenant.name}". Use /tenants/switch in the tenant app.`);
    } catch (e) {
        // handled by interceptor
    }
};

onMounted(load);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Tenants</h1>
                <p class="text-surface-600">Manage all customer workspaces</p>
            </div>
            <Button v-if="can(['platform.tenants.create'])" label="Create Tenant" icon="pi pi-plus" @click="openCreate" />
        </div>

        <!-- Summary cards -->
        <div class="mb-6 grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Total</p>
                <p class="mt-1 text-2xl font-bold">{{ summary.total }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Trial</p>
                <p class="mt-1 text-2xl font-bold text-info">{{ summary.trial }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Active</p>
                <p class="mt-1 text-2xl font-bold text-success">{{ summary.active }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Suspended</p>
                <p class="mt-1 text-2xl font-bold text-warning">{{ summary.suspended }}</p>
            </div>
            <div class="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow-sm">
                <p class="text-sm text-surface-500">Provisioning Failed</p>
                <p class="mt-1 text-2xl font-bold text-danger">{{ summary.provisioningFailed }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="filters.search" placeholder="Search name, slug, owner, email" class="w-72" @input="onSearchInput" />
            </span>
            <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-44" @change="onStatusChange" />
            <Select v-model="filters.subscription_status" :options="subscriptionStatusOptions" optionLabel="label" optionValue="value" class="w-56" @change="onSubscriptionStatusChange" />
        </div>

        <DataTable :value="tenants" :loading="loading" paginator :rows="filters.per_page" :totalRecords="total" :lazy="true" @page="onPageChange" class="w-full" :rowClass="(data) => (data.is_archived ? 'opacity-60' : '')">
            <template #empty>
                <div class="py-12 text-center text-surface-400">No tenants found.</div>
            </template>

            <Column header="Tenant" style="min-width: 220px">
                <template #body="{ data }">
                    <button class="flex items-center gap-3 text-left hover:text-primary" @click="openTenant(data)">
                        <Avatar :label="(data.name || '?').slice(0, 1).toUpperCase()" shape="circle" class="bg-primary text-white" />
                        <div>
                            <div class="font-medium">{{ data.name }}</div>
                            <div class="text-xs text-surface-500">{{ data.slug }}</div>
                        </div>
                    </button>
                </template>
            </Column>

            <Column header="Status" style="min-width: 140px">
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                    <div v-if="data.status === 'provisioning_failed'" class="mt-1 truncate text-xs text-danger max-w-40" :title="data.provisioning_error">
                        {{ data.provisioning_error }}
                    </div>
                </template>
            </Column>

            <Column header="Owner" style="min-width: 180px">
                <template #body="{ data }">
                    <template v-if="data.owner">
                        <div class="font-medium">{{ data.owner.name || '—' }}</div>
                        <div class="text-xs text-surface-500">{{ data.owner.email }}</div>
                    </template>
                    <span v-else class="text-surface-400">—</span>
                </template>
            </Column>

            <Column header="Plan" style="min-width: 140px">
                <template #body="{ data }">
                    <template v-if="data.subscription?.plan">
                        <div>{{ data.subscription.plan.name }}</div>
                        <Tag :value="data.subscription.status_label" :severity="data.subscription.status_color" class="mt-1" />
                    </template>
                    <span v-else class="text-surface-400">No plan</span>
                </template>
            </Column>

            <Column field="members_count" header="Members" style="width: 110px" />

            <Column header="Created" style="min-width: 130px">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>

            <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="openTenant(data)" />
                        <Button v-if="can(['platform.tenants.manage']) && !data.is_archived" icon="pi pi-eye-slash" severity="secondary" text rounded title="Manage tenant context" @click="manageTenant(data, $event)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create Tenant Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="Create Tenant" :style="{ width: '560px' }" modal>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Company / Workspace Name *</label>
                    <InputText v-model="createForm.name" placeholder="Acme Inc." />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Owner Full Name *</label>
                    <InputText v-model="createForm.owner.name" placeholder="John Doe" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Owner Email *</label>
                    <InputText v-model="createForm.owner.email" type="email" placeholder="john@acme.com" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Subdomain</label>
                        <InputText v-model="createForm.subdomain" placeholder="auto-generated" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Plan</label>
                        <Select v-model="createForm.plan_id" :options="plans" optionLabel="name" optionValue="id" placeholder="Default plan" showClear />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Support Email</label>
                    <InputText v-model="createForm.support_email" type="email" placeholder="support@acme.com" />
                </div>
                <p class="text-xs text-surface-500">A tenant database is created immediately and provisioned (owner, defaults, trial subscription) synchronously.</p>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showCreateDialog = false" />
                <Button label="Create Tenant" icon="pi pi-plus" :loading="creating" @click="handleCreate" />
            </template>
        </Dialog>
    </div>
</template>
