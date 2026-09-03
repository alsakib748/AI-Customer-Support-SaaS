<!-- src/views/customers/CustomerList.vue -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useCustomerStore } from '@/stores/customer';
import { useAuthStore } from '@/stores/auth';
// import { toast } from 'vue3-toastify';

const customerStore = useCustomerStore();
const authStore = useAuthStore();

// ============================================
// STATE
// ============================================

const showFormDialog = ref(false);
const showDetailsDialog = ref(false);
const selectedCustomer = ref(null);
const tagInput = ref('');
const formMode = ref('create');
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    status: null,
    tag: null,
    sort: 'created_at',
    direction: 'desc',
    per_page: 20,
});

const form = reactive({
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company_name: '',
    status: 'active',
    tags: [],
    notes: '',
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => customerStore.loading);
const saving = computed(() => customerStore.saving);
const customers = computed(() => customerStore.customers);
const totalCustomers = computed(() => customerStore.totalCustomers);
const statistics = computed(() => customerStore.statistics);
const tags = computed(() => customerStore.tags);

const canCreateCustomers = computed(() => {
    return authStore.hasPermission('customers.create');
});

const canUpdateCustomers = computed(() => {
    return authStore.hasPermission('customers.update');
});

const canDeleteCustomers = computed(() => {
    return authStore.hasPermission('customers.delete');
});

// const canViewCustomers = computed(() => {
//     return authStore.hasPermission('customers.view');
// });

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Blocked', value: 'blocked' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    try {
        await Promise.all([
            customerStore.fetchCustomers({ ...filters }),
            customerStore.fetchStatistics(),
            customerStore.fetchTags(),
        ]);
    } catch (error) {
        console.error('Failed to load data:', error);
    }
};

const refreshData = () => {
    loadData();
};

const applyFilters = () => {
    customerStore.filters = { ...filters };
    customerStore.fetchCustomers();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: null,
        tag: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    customerStore.fetchCustomers({ ...filters });
};

const onSortChange = (event) => {
    filters.sort = event.sortField;
    filters.direction = event.sortOrder === 1 ? 'asc' : 'desc';
    applyFilters();
};

//  Open create modal
const openCreateModal = () => {
    formMode.value = 'create';
    resetForm();
    showFormDialog.value = true;
};

//  Open edit modal
const openEditModal = (customer) => {
    formMode.value = 'edit';
    form.id = customer.id;
    form.first_name = customer.first_name || '';
    form.last_name = customer.last_name || '';
    form.email = customer.email || '';
    form.phone = customer.phone || '';
    form.company_name = customer.company_name || '';
    form.status = customer.status || 'active';
    form.tags = [...(customer.tags || [])];
    form.notes = customer.notes || '';
    showFormDialog.value = true;
};

const viewCustomer = (customer) => {
    selectedCustomer.value = customer;
    showDetailsDialog.value = true;
};

const editCustomer = (customer) => {
    showDetailsDialog.value = false;
    openEditModal(customer);
};

const resetForm = () => {
    form.id = null;
    form.first_name = '';
    form.last_name = '';
    form.email = '';
    form.phone = '';
    form.company_name = '';
    form.status = 'active';
    form.tags = [];
    form.notes = '';
    tagInput.value = '';
};

const addTagToForm = () => {
    const tag = tagInput.value.trim();
    if (tag && !form.tags.includes(tag)) {
        form.tags.push(tag);
        tagInput.value = '';
    }
};

const removeTagFromForm = (tag) => {
    form.tags = form.tags.filter(t => t !== tag);
};

const handleSubmit = async () => {
    try {
        const data = {
            first_name: form.first_name,
            last_name: form.last_name,
            email: form.email || null,
            phone: form.phone || null,
            company_name: form.company_name || null,
            status: form.status,
            tags: form.tags,
            notes: form.notes || null,
        };

        if (formMode.value === 'create') {
            await customerStore.createCustomer(data);
            // toast.success('Customer created successfully 🎉');
        } else {
            await customerStore.updateCustomer(form.id, data);
            // toast.success('Customer updated successfully 🎉');
        }

        showFormDialog.value = false;
        resetForm();
        await loadData();

    } catch (error) {
        // Error handled in store
        console.error('Form submission error:', error);
    }
};

const handleBlock = (customer) => {
    const reason = prompt('Enter reason for blocking (optional):');
    if (reason !== null) {
        customerStore.blockCustomer(customer.id, reason || null);
    }
};

const handleUnblock = (customer) => {
    if (confirm(`Are you sure you want to unblock ${customer.full_name || customer.display_name}?`)) {
        customerStore.unblockCustomer(customer.id);
    }
};

const confirmDelete = (customer) => {
    if (confirm(`Are you sure you want to delete ${customer.full_name || customer.display_name}?`)) {
        customerStore.deleteCustomer(customer.id);
    }
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
    return customerStore.getFieldError(field);
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
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Customers</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage your customers and their support history</p>
            </div>
            <div class="flex gap-3">
                <!-- Fixed: Use openCreateModal instead of showCreateDialog -->
                <Button label="Add Customer" icon="pi pi-user-plus" severity="primary" @click="openCreateModal"
                    v-if="canCreateCustomers" />
                <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="refreshData"
                    :loading="loading" />
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalCustomers }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Customers</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ statistics.active || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Active</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ statistics.inactive || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Inactive</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">{{ statistics.blocked || 0 }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Blocked</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="filters.search" placeholder="Search customers..." class="w-full"
                    @input="applyFilters" />
            </div>
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <div class="w-48">
                <Select v-model="filters.tag" :options="tags" placeholder="Tag" class="w-full" @change="applyFilters"
                    clearable />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <!-- Customers Table -->
        <DataTable :value="customers" :loading="loading" paginator :rows="filters.per_page"
            :totalRecords="totalCustomers" :lazy="true" @page="onPageChange" @sort="onSortChange" class="w-full"
            v-model:sortField="filters.sort" v-model:sortOrder="sortOrder">
            <Column selectionMode="multiple" v-if="canDeleteCustomers" style="width: 3rem" />

            <Column field="full_name" header="Name" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-3">
                        <Avatar :label="data.initials || '?'" :image="data.avatar_url" shape="circle" size="large"
                            :class="{
                                'bg-success': data.status === 'active',
                                'bg-warning': data.status === 'inactive',
                                'bg-danger': data.status === 'blocked',
                            }" />
                        <div>
                            <div class="font-medium">{{ data.full_name || data.display_name }}</div>
                            <div class="text-sm text-surface-500">{{ data.email || data.phone || '—' }}</div>
                        </div>
                    </div>
                </template>
            </Column>

            <Column field="email" header="Email" sortable>
                <template #body="{ data }">
                    {{ data.email || '—' }}
                </template>
            </Column>

            <Column field="phone" header="Phone" sortable>
                <template #body="{ data }">
                    {{ data.phone || '—' }}
                </template>
            </Column>

            <Column field="company_name" header="Company" sortable>
                <template #body="{ data }">
                    {{ data.company_name || '—' }}
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>

            <Column field="total_conversations" header="Conversations" sortable>
                <template #body="{ data }">
                    {{ data.total_conversations }}
                </template>
            </Column>

            <Column header="Tags">
                <template #body="{ data }">
                    <div class="flex flex-wrap gap-1">
                        <Tag v-for="tag in data.tags" :key="tag" :value="tag" severity="info" class="text-xs" />
                        <span v-if="!data.tags?.length" class="text-sm text-surface-400">—</span>
                    </div>
                </template>
            </Column>

            <Column header="Actions" style="width: 160px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewCustomer(data)"
                            tooltip="View Details" />
                        <Button v-if="canUpdateCustomers" icon="pi pi-pencil" severity="warning" text rounded
                            @click="editCustomer(data)" tooltip="Edit" />
                        <Button v-if="data.status === 'blocked' && canUpdateCustomers" icon="pi pi-unlock"
                            severity="success" text rounded @click="handleUnblock(data)" tooltip="Unblock" />
                        <Button v-if="data.status !== 'blocked' && canUpdateCustomers" icon="pi pi-lock"
                            severity="danger" text rounded @click="handleBlock(data)" tooltip="Block" />
                        <Button v-if="canDeleteCustomers" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmDelete(data)" tooltip="Delete" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- ✅ Create/Edit Dialog -->
        <Dialog v-model:visible="showFormDialog" :header="formMode === 'create' ? 'Add Customer' : 'Edit Customer'"
            :style="{ width: '600px' }" modal>
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">First Name *</label>
                        <InputText v-model="form.first_name" class="w-full"
                            :class="{ 'p-invalid': getFieldError('first_name') }" placeholder="John" />
                        <small v-if="getFieldError('first_name')" class="text-red-500">
                            {{ getFieldError('first_name') }}
                        </small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Last Name</label>
                        <InputText v-model="form.last_name" class="w-full" placeholder="Smith" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <InputText v-model="form.email" type="email" class="w-full"
                        :class="{ 'p-invalid': getFieldError('email') }" placeholder="john@example.com" />
                    <small v-if="getFieldError('email')" class="text-red-500">
                        {{ getFieldError('email') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <InputText v-model="form.phone" class="w-full" placeholder="+1 234 567 8900" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Company</label>
                    <InputText v-model="form.company_name" class="w-full" placeholder="ABC Corporation" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Tags</label>
                    <div class="flex gap-2 mb-2">
                        <InputText v-model="tagInput" placeholder="Add a tag" class="flex-1"
                            @keydown.enter.prevent="addTagToForm" />
                        <Button icon="pi pi-plus" severity="secondary" @click="addTagToForm" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Chip v-for="tag in form.tags" :key="tag" :label="tag" removable
                            @remove="removeTagFromForm(tag)" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <Textarea v-model="form.notes" class="w-full" rows="3"
                        placeholder="Add notes about this customer..." />
                </div>

                <div v-if="getFieldError('general')" class="text-red-500 text-sm">
                    {{ getFieldError('general') }}
                </div>
            </form>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="showFormDialog = false" />
                <Button :label="formMode === 'create' ? 'Create' : 'Save'" icon="pi pi-save" severity="primary"
                    :loading="saving" @click="handleSubmit" />
            </template>
        </Dialog>

        <!-- Customer Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" header="Customer Details" :style="{ width: '700px' }" modal>
            <div v-if="selectedCustomer" class="space-y-4">
                <div class="flex items-start gap-4">
                    <Avatar :label="selectedCustomer.initials || '?'" :image="selectedCustomer.avatar_url"
                        shape="circle" size="xlarge" :class="{
                            'bg-success': selectedCustomer.status === 'active',
                            'bg-warning': selectedCustomer.status === 'inactive',
                            'bg-danger': selectedCustomer.status === 'blocked',
                        }" />
                    <div class="flex-1">
                        <div class="text-xl font-bold">{{ selectedCustomer.full_name || selectedCustomer.display_name }}
                        </div>
                        <div class="text-surface-500">{{ selectedCustomer.email || 'No email' }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <Tag :value="selectedCustomer.status_label" :severity="selectedCustomer.status_color" />
                            <span v-if="selectedCustomer.company_name" class="text-sm text-surface-500">
                                {{ selectedCustomer.company_name }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="canUpdateCustomers" icon="pi pi-pencil" severity="warning"
                            @click="editCustomer(selectedCustomer)" />
                        <Button icon="pi pi-times" severity="secondary" @click="showDetailsDialog = false" />
                    </div>
                </div>

                <Divider />

                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-primary-50 dark:bg-primary-950 rounded-lg">
                        <div class="text-2xl font-bold text-primary">{{ selectedCustomer.total_conversations }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Conversations</div>
                    </div>
                    <div class="text-center p-3 bg-info-50 dark:bg-info-950 rounded-lg">
                        <div class="text-2xl font-bold text-info">{{ selectedCustomer.total_tickets }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Tickets</div>
                    </div>
                    <div class="text-center p-3 bg-success-50 dark:bg-success-950 rounded-lg">
                        <div class="text-2xl font-bold text-success">
                            {{ selectedCustomer.satisfaction_score ?? '—' }}
                        </div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Satisfaction</div>
                    </div>
                </div>

                <div v-if="selectedCustomer.tags?.length">
                    <label class="text-sm text-surface-500">Tags</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <Tag v-for="tag in selectedCustomer.tags" :key="tag" :value="tag" severity="info" />
                    </div>
                </div>

                <div v-if="selectedCustomer.notes">
                    <label class="text-sm text-surface-500">Notes</label>
                    <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg mt-1 whitespace-pre-wrap">
                        {{ selectedCustomer.notes }}
                    </div>
                </div>

                <div class="text-sm text-surface-500">
                    <div>Created: {{ formatDate(selectedCustomer.created_at) }}</div>
                    <div v-if="selectedCustomer.last_contacted_at">
                        Last Contacted: {{ formatDate(selectedCustomer.last_contacted_at) }}
                    </div>
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
