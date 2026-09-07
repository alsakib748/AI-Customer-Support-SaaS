<!-- src/views/settings/ChatWidgets.vue -->


<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useWidgetStore } from '@/stores/widget';
import { useAuthStore } from '@/stores/auth';
// import { useToast } from 'primevue/usetoast';

const widgetStore = useWidgetStore();
const authStore = useAuthStore();
// const toast = useToast();

// ============================================
// STATE
// ============================================

const showFormDialog = ref(false);
const showInstallDialog = ref(false);
const showDetailsDialog = ref(false);
const formMode = ref('create');
const originInput = ref('');
const installationCode = ref('');
const selectedWidget = ref(null);
const sortOrder = ref(1);

const filters = reactive({
    search: '',
    status: null,
    per_page: 20,
});

const form = reactive({
    id: null,
    name: '',
    header_title: 'Chat with us',
    welcome_message: '',
    offline_message: '',
    position: 'bottom-right',
    primary_color: '#4F46E5',
    show_branding: true,
    require_name: false,
    require_email: false,
    require_phone: false,
    allowed_origins: [],
});

// ============================================
// COMPUTED
// ============================================

const loading = computed(() => widgetStore.loading);
const saving = computed(() => widgetStore.saving);
const widgets = computed(() => widgetStore.widgets);
const totalWidgets = computed(() => widgetStore.totalWidgets);
const activeWidgets = computed(() => widgetStore.activeWidgets);
const disabledWidgets = computed(() => widgetStore.disabledWidgets);

const canViewWidgets = computed(() => authStore.hasPermission('widgets.view'));
const canCreateWidgets = computed(() => authStore.hasPermission('widgets.create'));
const canUpdateWidgets = computed(() => authStore.hasPermission('widgets.update'));
const canDeleteWidgets = computed(() => authStore.hasPermission('widgets.delete'));
const canManageWidgets = computed(() => authStore.hasPermission('widgets.manage'));

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Disabled', value: 'disabled' },
    { label: 'Inactive', value: 'inactive' },
];

const positionOptions = [
    { label: 'Bottom Right', value: 'bottom-right' },
    { label: 'Bottom Left', value: 'bottom-left' },
    { label: 'Top Right', value: 'top-right' },
    { label: 'Top Left', value: 'top-left' },
];

// ============================================
// METHODS
// ============================================

const loadData = async () => {
    await widgetStore.fetchWidgets({ ...filters });
};

const applyFilters = () => {
    widgetStore.filters = { ...filters };
    widgetStore.fetchWidgets();
};

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: null,
        per_page: 20,
    });
    applyFilters();
};

const onPageChange = (event) => {
    filters.per_page = event.rows;
    widgetStore.fetchWidgets({ ...filters });
};

const openCreateDialog = () => {
    formMode.value = 'create';
    resetForm();
    showFormDialog.value = true;
};

const openEditDialog = (widget) => {
    formMode.value = 'edit';
    form.id = widget.id;
    form.name = widget.name || '';
    form.header_title = widget.header_title || 'Chat with us';
    form.welcome_message = widget.welcome_message || '';
    form.offline_message = widget.offline_message || '';
    form.position = widget.position || 'bottom-right';
    form.primary_color = widget.primary_color || '#4F46E5';
    form.show_branding = widget.show_branding !== undefined ? widget.show_branding : true;
    form.require_name = widget.require_name || false;
    form.require_email = widget.require_email || false;
    form.require_phone = widget.require_phone || false;
    form.allowed_origins = widget.allowed_origins || [];
    showDetailsDialog.value = false;
    showFormDialog.value = true;
};

const viewWidget = (widget) => {
    selectedWidget.value = widget;
    showDetailsDialog.value = true;
};

const resetForm = () => {
    form.id = null;
    form.name = '';
    form.header_title = 'Chat with us';
    form.welcome_message = '';
    form.offline_message = '';
    form.position = 'bottom-right';
    form.primary_color = '#4F46E5';
    form.show_branding = true;
    form.require_name = false;
    form.require_email = false;
    form.require_phone = false;
    form.allowed_origins = [];
    originInput.value = '';
};

const addOrigin = () => {
    const origin = originInput.value.trim();
    if (origin && !form.allowed_origins.includes(origin)) {
        form.allowed_origins.push(origin);
        originInput.value = '';
    }
};

const removeOrigin = (origin) => {
    form.allowed_origins = form.allowed_origins.filter(o => o !== origin);
};

const handleSubmit = async () => {
    try {
        const data = {
            name: form.name,
            header_title: form.header_title,
            welcome_message: form.welcome_message,
            offline_message: form.offline_message,
            position: form.position,
            primary_color: form.primary_color,
            show_branding: form.show_branding,
            require_name: form.require_name,
            require_email: form.require_email,
            require_phone: form.require_phone,
            allowed_origins: form.allowed_origins,
        };

        if (formMode.value === 'create') {
            await widgetStore.createWidget(data);
        } else {
            await widgetStore.updateWidget(form.id, data);
        }

        showFormDialog.value = false;
        resetForm();
        await loadData();

    } catch (error) {
        // Error handled in store
    }
};

const enableWidget = async (widget) => {
    try {
        await widgetStore.enableWidget(widget.id);
        await loadData();
        if (selectedWidget.value?.id === widget.id) {
            selectedWidget.value = widgetStore.currentWidget;
        }
        // toast.success('Widget enabled successfully ✅');
    } catch (error) {
        // Error handled in store
    }
};

const disableWidget = async (widget) => {
    if (!confirm('Are you sure you want to disable this widget?')) return;
    try {
        await widgetStore.disableWidget(widget.id);
        await loadData();
        if (selectedWidget.value?.id === widget.id) {
            selectedWidget.value = widgetStore.currentWidget;
        }
        // toast.success('Widget disabled successfully 🔒');
    } catch (error) {
        // Error handled in store
    }
};

const regenerateKey = async (widget) => {
    if (!confirm('Are you sure you want to regenerate the public key? This will break existing widget installations.')) return;
    try {
        await widgetStore.regenerateKey(widget.id);
        await loadData();
        // toast.success('Key regenerated successfully 🔑');
    } catch (error) {
        // Error handled in store
    }
};

const confirmDelete = (widget) => {
    if (confirm(`Are you sure you want to delete the widget "${widget.name}"?`)) {
        widgetStore.deleteWidget(widget.id);
    }
};

const showInstallationCode = async (widget) => {
    try {
        const data = await widgetStore.getInstallationCode(widget.id);
        installationCode.value = data.code;
        showInstallDialog.value = true;
    } catch (error) {
        // Error handled in store
    }
};

const copyCode = () => {
    navigator.clipboard.writeText(installationCode.value);
    // toast.success('Code copied to clipboard! 📋');
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getFieldError = (field) => {
    return widgetStore.getFieldError(field);
};

// ============================================
// LIFECYCLE
// ============================================

onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">Chat Widgets</h1>
                <p class="text-surface-600 dark:text-surface-400">Manage chat widgets for your website</p>
            </div>
            <Button v-if="canCreateWidgets" label="Create Widget" icon="pi pi-plus" severity="primary"
                @click="openCreateDialog" />
            <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="loadData"
                :loading="loading" />
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ totalWidgets }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Total Widgets</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">{{ activeWidgets }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Active</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-danger">{{ disabledWidgets }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Disabled</div>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning">{{ totalWidgets - activeWidgets - disabledWidgets
                        }}</div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">Inactive</div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px]">
                <InputText v-model="filters.search" placeholder="Search widgets..." class="w-full"
                    @input="applyFilters" />
            </div>
            <div class="w-48">
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="Status" class="w-full" @change="applyFilters" clearable />
            </div>
            <Button icon="pi pi-times" label="Clear" severity="secondary" outlined @click="clearFilters" />
        </div>

        <!-- Widgets Table -->
        <DataTable :value="widgets" :loading="loading" paginator :rows="filters.per_page" :totalRecords="totalWidgets"
            :lazy="true" @page="onPageChange" class="w-full">
            <Column field="name" header="Name" sortable>
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.name }}</div>
                        <div class="text-sm text-surface-500 font-mono">{{ data.public_key }}</div>
                    </div>
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <Tag :value="data.status_label" :severity="data.status_color" />
                </template>
            </Column>

            <Column field="header_title" header="Title" sortable>
                <template #body="{ data }">
                    {{ data.header_title || '—' }}
                </template>
            </Column>

            <Column field="position" header="Position" sortable>
                <template #body="{ data }">
                    {{ data.position_label || data.position }}
                </template>
            </Column>

            <Column field="created_at" header="Created" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.created_at) }}
                </template>
            </Column>

            <Column field="sessions_count" header="Sessions" sortable>
                <template #body="{ data }">
                    {{ data.sessions_count || 0 }}
                </template>
            </Column>

            <Column header="Actions" style="width: 320px">
                <template #body="{ data }">
                    <div class="flex gap-1 flex-wrap">
                        <Button icon="pi pi-eye" severity="info" text rounded @click="viewWidget(data)"
                            tooltip="View Details" />
                        <Button v-if="canUpdateWidgets && data.status === 'disabled'" icon="pi pi-check"
                            severity="success" text rounded @click="enableWidget(data)" tooltip="Enable" />
                        <Button v-if="canUpdateWidgets && data.status === 'active'" icon="pi pi-times" severity="danger"
                            text rounded @click="disableWidget(data)" tooltip="Disable" />
                        <Button v-if="canUpdateWidgets" icon="pi pi-pencil" severity="warning" text rounded
                            @click="openEditDialog(data)" tooltip="Edit" />
                        <Button v-if="canUpdateWidgets" icon="pi pi-key" severity="secondary" text rounded
                            @click="regenerateKey(data)" tooltip="Regenerate Key" />
                        <Button v-if="canDeleteWidgets" icon="pi pi-trash" severity="danger" text rounded
                            @click="confirmDelete(data)" tooltip="Delete" />
                        <Button icon="pi pi-code" severity="secondary" text rounded @click="showInstallationCode(data)"
                            tooltip="Installation Code" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="showFormDialog" :header="formMode === 'create' ? 'Create Widget' : 'Edit Widget'"
            :style="{ width: '600px' }" modal>
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Basic Info -->
                <div>
                    <label class="block text-sm font-medium mb-1">Widget Name *</label>
                    <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': getFieldError('name') }"
                        placeholder="Main Website" />
                    <small v-if="getFieldError('name')" class="text-red-500">
                        {{ getFieldError('name') }}
                    </small>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Header Title</label>
                    <InputText v-model="form.header_title" class="w-full" placeholder="Chat with us" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Welcome Message</label>
                    <Textarea v-model="form.welcome_message" class="w-full" rows="2"
                        placeholder="Hi! How can we help you today?" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Offline Message</label>
                    <Textarea v-model="form.offline_message" class="w-full" rows="2"
                        placeholder="We're offline. Leave a message and we'll get back to you." />
                </div>

                <!-- Appearance -->
                <div>
                    <label class="block text-sm font-medium mb-1">Position</label>
                    <Select v-model="form.position" :options="positionOptions" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Primary Color</label>
                    <div class="flex gap-3">
                        <InputText v-model="form.primary_color" class="flex-1" placeholder="#4F46E5" />
                        <input type="color" v-model="form.primary_color"
                            class="w-12 h-10 rounded border cursor-pointer" />
                    </div>
                </div>

                <!-- Requirements -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.require_name" binary />
                        <label class="text-sm">Require Name</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.require_email" binary />
                        <label class="text-sm">Require Email</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.require_phone" binary />
                        <label class="text-sm">Require Phone</label>
                    </div>
                </div>

                <!-- Security -->
                <div>
                    <label class="block text-sm font-medium mb-1">Allowed Origins</label>
                    <div class="flex gap-2 mb-2">
                        <InputText v-model="originInput" placeholder="https://example.com" class="flex-1"
                            @keydown.enter.prevent="addOrigin" />
                        <Button icon="pi pi-plus" severity="secondary" @click="addOrigin" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Chip v-for="origin in form.allowed_origins" :key="origin" :label="origin" removable
                            @remove="removeOrigin(origin)" />
                    </div>
                    <small class="text-surface-500">Leave empty to allow all origins</small>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.show_branding" binary />
                    <label class="text-sm">Show Branding</label>
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

        <!-- Widget Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" header="Widget Details" :style="{ width: '600px' }" modal>
            <div v-if="selectedWidget" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold">{{ selectedWidget.name }}</h3>
                        <div class="flex items-center gap-2 mt-1">
                            <Tag :value="selectedWidget.status_label" :severity="selectedWidget.status_color" />
                            <span class="text-sm text-surface-500 font-mono">{{ selectedWidget.public_key }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button v-if="canUpdateWidgets && selectedWidget.status === 'disabled'" icon="pi pi-check"
                            label="Enable" severity="success" size="small" @click="enableWidget(selectedWidget)" />
                        <Button v-if="canUpdateWidgets && selectedWidget.status === 'active'" icon="pi pi-times"
                            label="Disable" severity="danger" size="small" @click="disableWidget(selectedWidget)" />
                    </div>
                </div>

                <Divider />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-surface-500">Header Title</label>
                        <div class="font-medium">{{ selectedWidget.header_title || '—' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Position</label>
                        <div class="font-medium">{{ selectedWidget.position_label || selectedWidget.position }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Primary Color</label>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ selectedWidget.primary_color || '—' }}</span>
                            <span v-if="selectedWidget.primary_color" class="w-6 h-6 rounded border"
                                :style="{ backgroundColor: selectedWidget.primary_color }" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Show Branding</label>
                        <div class="font-medium">{{ selectedWidget.show_branding ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Require Name</label>
                        <div class="font-medium">{{ selectedWidget.require_name ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Require Email</label>
                        <div class="font-medium">{{ selectedWidget.require_email ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Require Phone</label>
                        <div class="font-medium">{{ selectedWidget.require_phone ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <label class="text-sm text-surface-500">Created</label>
                        <div class="font-medium">{{ formatDate(selectedWidget.created_at) }}</div>
                    </div>
                </div>

                <div v-if="selectedWidget.allowed_origins?.length">
                    <label class="text-sm text-surface-500">Allowed Origins</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <Tag v-for="origin in selectedWidget.allowed_origins" :key="origin" :value="origin"
                            severity="info" />
                    </div>
                </div>

                <div v-if="selectedWidget.welcome_message">
                    <label class="text-sm text-surface-500">Welcome Message</label>
                    <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg mt-1">
                        {{ selectedWidget.welcome_message }}
                    </div>
                </div>

                <div v-if="selectedWidget.offline_message">
                    <label class="text-sm text-surface-500">Offline Message</label>
                    <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg mt-1">
                        {{ selectedWidget.offline_message }}
                    </div>
                </div>
            </div>

            <template #footer>
                <Button v-if="canUpdateWidgets && selectedWidget" icon="pi pi-pencil" label="Edit" severity="warning"
                    @click="openEditDialog(selectedWidget)" />
                <Button icon="pi pi-code" label="Installation Code" severity="secondary"
                    @click="showInstallationCode(selectedWidget)" />
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showDetailsDialog = false" />
            </template>
        </Dialog>

        <!-- Installation Code Dialog -->
        <Dialog v-model:visible="showInstallDialog" header="Installation Code" :style="{ width: '600px' }" modal>
            <div class="space-y-4">
                <p class="text-sm text-surface-600">
                    Copy and paste this code into your website's HTML before the closing <code>&lt;/body&gt;</code> tag.
                </p>
                <div class="relative">
                    <pre class="bg-surface-100 dark:bg-surface-800 p-4 rounded-lg text-sm overflow-x-auto">
                <code>{{ installationCode }}</code>
            </pre>
                    <Button icon="pi pi-copy" severity="secondary" text class="absolute top-2 right-2" @click="copyCode"
                        tooltip="Copy" />
                </div>
                <div class="text-sm text-surface-500">
                    <i class="pi pi-info-circle mr-1"></i>
                    This widget will work on the allowed origins you configured.
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showInstallDialog = false" />
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

:deep(.p-dialog .p-dialog-header) {
    padding: 1.5rem 1.5rem 0;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1.5rem;
}
</style>
