<script setup>

import { ref, reactive, onMounted } from 'vue';
import { useWorkspaceStore } from '@/stores/workspace';
// import { toast } from 'vue3-toastify';

const workspaceStore = useWorkspaceStore();

// State
const loading = ref(true);
const saving = ref(false);
const showBusinessHours = ref(false);
const logoInput = ref(null);

// Form data
const form = reactive({
    name: '',
    industry: '',
    logo: '',
    support_email: '',
    support_phone: '',
    timezone: 'UTC',
    default_language: 'en',
});

// Business Hours
const weekDays = [
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
];

const businessHours = reactive({
    monday: { enabled: true, open: '09:00', close: '18:00' },
    tuesday: { enabled: true, open: '09:00', close: '18:00' },
    wednesday: { enabled: true, open: '09:00', close: '18:00' },
    thursday: { enabled: true, open: '09:00', close: '18:00' },
    friday: { enabled: true, open: '09:00', close: '18:00' },
    saturday: { enabled: false, open: '09:00', close: '18:00' },
    sunday: { enabled: false, open: '09:00', close: '18:00' },
});

// Statistics
const statistics = reactive({
    team_members: 0,
    customers: 0,
    conversations: 0,
    tickets: 0,
});

// Options
const industries = [
    { label: 'Technology', value: 'Technology' },
    { label: 'E-commerce', value: 'E-commerce' },
    { label: 'Healthcare', value: 'Healthcare' },
    { label: 'Education', value: 'Education' },
    { label: 'Finance', value: 'Finance' },
    { label: 'Manufacturing', value: 'Manufacturing' },
    { label: 'Retail', value: 'Retail' },
    { label: 'Other', value: 'Other' },
];

const timezones = [
    { label: 'UTC', value: 'UTC' },
    { label: 'Asia/Dhaka', value: 'Asia/Dhaka' },
    { label: 'Asia/Kolkata', value: 'Asia/Kolkata' },
    { label: 'America/New_York', value: 'America/New_York' },
    { label: 'America/Los_Angeles', value: 'America/Los_Angeles' },
    { label: 'Europe/London', value: 'Europe/London' },
    { label: 'Europe/Paris', value: 'Europe/Paris' },
    { label: 'Australia/Sydney', value: 'Australia/Sydney' },
    { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
    { label: 'Asia/Singapore', value: 'Asia/Singapore' },
];

const languages = [
    { label: 'English', value: 'en' },
    { label: 'বাংলা (Bengali)', value: 'bn' },
    { label: 'Español', value: 'es' },
    { label: 'Français', value: 'fr' },
    { label: 'Deutsch', value: 'de' },
    { label: '日本語', value: 'ja' },
    { label: '中文', value: 'zh' },
];

// Load workspace data
const loadWorkspace = async () => {
    loading.value = true;
    try {
        await workspaceStore.fetchWorkspace();
        if (workspaceStore.workspace) {
            const w = workspaceStore.workspace;
            Object.assign(form, {
                name: w.name || '',
                industry: w.industry || '',
                logo: w.logo || '',
                support_email: w.support_email || '',
                support_phone: w.support_phone || '',
                timezone: w.timezone || 'UTC',
                default_language: w.default_language || 'en',
            });

            // Load business hours if exists
            if (w.business_hours) {
                Object.keys(businessHours).forEach(day => {
                    if (w.business_hours[day]) {
                        businessHours[day] = {
                            ...businessHours[day],
                            ...w.business_hours[day],
                        };
                    }
                });
            }
        }

        // Load statistics
        await loadStatistics();

    } catch (error) {
        console.error('Failed to load workspace:', error);
        // toast.error('Failed to load workspace settings');
    } finally {
        loading.value = false;
    }
};

// Load statistics
const loadStatistics = async () => {
    try {
        const stats = await workspaceStore.fetchStatistics();
        if (stats) {
            Object.assign(statistics, stats);
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
};

// Handle form submission
const handleSubmit = async () => {
    saving.value = true;
    try {
        await workspaceStore.updateWorkspace({
            name: form.name,
            industry: form.industry,
            support_email: form.support_email,
            support_phone: form.support_phone,
            timezone: form.timezone,
            default_language: form.default_language,
        });

        // toast.success('Workspace updated successfully.');

    } catch (error) {
        console.error('Failed to update workspace:', error);
        // toast.error(error.response?.data?.message || 'Failed to update workspace');
    } finally {
        saving.value = false;
    }
};

// Handle logo upload
const handleLogoUpload = () => {
    logoInput.value.click();
};

const handleLogoChange = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    try {
        await workspaceStore.updateLogo(file);
        form.logo = workspaceStore.workspace?.logo || '';

        // toast.success('Logo updated successfully');

    } catch (error) {
        console.error('Failed to upload logo:', error);
        // toast.error('Failed to upload logo');
    }

    logoInput.value.value = '';
};

// Handle logo delete
const handleLogoDelete = async () => {
    try {
        await workspaceStore.deleteLogo();
        form.logo = '';

        // toast.success('Logo removed successfully');

    } catch (error) {
        console.error('Failed to delete logo:', error);
        // toast.error('Failed to remove logo');
    }
};

// Handle business hours save
const handleBusinessHoursSave = async () => {
    try {
        await workspaceStore.updateBusinessHours(businessHours);

        // toast.success('Business hours updated successfully');

    } catch (error) {
        console.error('Failed to update business hours:', error);
        // toast.error('Failed to update business hours');
    }
};

// Reset form
const resetForm = () => {
    if (workspaceStore.workspace) {
        const w = workspaceStore.workspace;
        Object.assign(form, {
            name: w.name || '',
            industry: w.industry || '',
            logo: w.logo || '',
            support_email: w.support_email || '',
            support_phone: w.support_phone || '',
            timezone: w.timezone || 'UTC',
            default_language: w.default_language || 'en',
        });
    }
};

// Load data on mount
onMounted(() => {
    loadWorkspace();
});
</script>

<!-- src/views/settings/Workspace.vue -->
<template>
    <div class="max-w-4xl mx-auto p-6">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">
                Workspace Settings
            </h1>
            <p class="text-surface-600 dark:text-surface-400 mt-2">
                Manage your workspace information and preferences
            </p>
        </div>

        <!-- Loading State -->
        <!-- <div v-if="loading" class="flex justify-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div> -->

        <!-- Workspace Form -->
        <!-- <form v-else @submit.prevent="handleSubmit" class="space-y-6"> -->
        <form @submit.prevent="handleSubmit" class="space-y-6">

            <!-- ============================================ -->
            <!-- GENERAL INFORMATION -->
            <!-- ============================================ -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-building text-primary"></i>
                        <span>General Information</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Workspace Name -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Workspace Name <span class="text-red-500">*</span>
                            </label>
                            <InputText v-model="form.name" class="w-full"
                                :class="{ 'p-invalid': workspaceStore.getFieldError('name') }"
                                placeholder="Enter workspace name" />
                            <small v-if="workspaceStore.getFieldError('name')" class="text-red-500 block mt-1">
                                {{ workspaceStore.getFieldError('name') }}
                            </small>
                        </div>

                        <!-- Industry -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Industry
                            </label>
                            <Select v-model="form.industry" :options="industries" optionLabel="label"
                                optionValue="value" placeholder="Select Industry" class="w-full" />
                        </div>

                        <!-- Logo Upload -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Logo
                            </label>
                            <div class="flex items-center gap-4">
                                <!-- Logo Preview -->
                                <div class="w-16 h-16 rounded-lg border-2 border-dashed border-surface-300 dark:border-surface-600 flex items-center justify-center overflow-hidden"
                                    :class="{ 'border-primary': form.logo }">
                                    <img v-if="form.logo" :src="form.logo" alt="Logo"
                                        class="w-full h-full object-cover" />
                                    <i v-else class="pi pi-image text-2xl text-surface-400"></i>
                                </div>

                                <!-- Upload Buttons -->
                                <div class="flex gap-2">
                                    <Button type="button" icon="pi pi-upload" label="Upload" severity="primary"
                                        size="small" @click="handleLogoUpload" />
                                    <Button v-if="form.logo" type="button" icon="pi pi-trash" label="Remove"
                                        severity="danger" size="small" outlined @click="handleLogoDelete" />
                                </div>
                                <input type="file" ref="logoInput" accept="image/*" class="hidden"
                                    @change="handleLogoChange" />
                            </div>
                            <small class="text-surface-500 dark:text-surface-400 block mt-1">
                                Recommended: Square image, max 2MB
                            </small>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ============================================ -->
            <!-- CONTACT INFORMATION -->
            <!-- ============================================ -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-phone text-primary"></i>
                        <span>Contact Information</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Support Email -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Support Email
                            </label>
                            <InputText v-model="form.support_email" type="email" class="w-full"
                                :class="{ 'p-invalid': workspaceStore.getFieldError('support_email') }"
                                placeholder="support@example.com" />
                            <small v-if="workspaceStore.getFieldError('support_email')" class="text-red-500 block mt-1">
                                {{ workspaceStore.getFieldError('support_email') }}
                            </small>
                        </div>

                        <!-- Support Phone -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Support Phone
                            </label>
                            <InputText v-model="form.support_phone" type="tel" class="w-full"
                                placeholder="+1 234 567 8900" />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ============================================ -->
            <!-- LOCALIZATION -->
            <!-- ============================================ -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-globe text-primary"></i>
                        <span>Localization</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Timezone -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Timezone <span class="text-red-500">*</span>
                            </label>
                            <Select v-model="form.timezone" :options="timezones" optionLabel="label" optionValue="value"
                                placeholder="Select Timezone" class="w-full"
                                :class="{ 'p-invalid': workspaceStore.getFieldError('timezone') }" />
                            <small v-if="workspaceStore.getFieldError('timezone')" class="text-red-500 block mt-1">
                                {{ workspaceStore.getFieldError('timezone') }}
                            </small>
                        </div>

                        <!-- Default Language -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Default Language <span class="text-red-500">*</span>
                            </label>
                            <Select v-model="form.default_language" :options="languages" optionLabel="label"
                                optionValue="value" placeholder="Select Language" class="w-full"
                                :class="{ 'p-invalid': workspaceStore.getFieldError('default_language') }" />
                            <small v-if="workspaceStore.getFieldError('default_language')"
                                class="text-red-500 block mt-1">
                                {{ workspaceStore.getFieldError('default_language') }}
                            </small>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ============================================ -->
            <!-- BUSINESS HOURS (Optional - Collapsible) -->
            <!-- ============================================ -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-clock text-primary"></i>
                            <span>Business Hours</span>
                        </div>
                        <Button type="button" :label="showBusinessHours ? 'Hide' : 'Configure'" severity="secondary"
                            text size="small" @click="showBusinessHours = !showBusinessHours" />
                    </div>
                </template>
                <template #content v-if="showBusinessHours">
                    <div class="space-y-4">
                        <div v-for="day in weekDays" :key="day.key"
                            class="flex items-center gap-4 p-3 rounded-lg border border-surface-200 dark:border-surface-700">
                            <div class="w-28 font-medium text-surface-700 dark:text-surface-300">
                                {{ day.label }}
                            </div>
                            <div class="flex items-center gap-3 flex-1">
                                <ToggleSwitch v-model="businessHours[day.key].enabled" class="mr-2" />
                                <span class="text-sm text-surface-500 dark:text-surface-400">
                                    {{ businessHours[day.key].enabled ? 'Open' : 'Closed' }}
                                </span>
                            </div>
                            <div v-if="businessHours[day.key].enabled" class="flex items-center gap-3">
                                <InputMask v-model="businessHours[day.key].open" mask="99:99" placeholder="09:00"
                                    class="w-24" />
                                <span class="text-surface-500">to</span>
                                <InputMask v-model="businessHours[day.key].close" mask="99:99" placeholder="18:00"
                                    class="w-24" />
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <Button type="button" label="Save Business Hours" severity="secondary"
                                @click="handleBusinessHoursSave" />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ============================================ -->
            <!-- STATISTICS -->
            <!-- ============================================ -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-chart-bar text-primary"></i>
                        <span>Workspace Statistics</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-primary-50 dark:bg-primary-950 rounded-lg">
                            <div class="text-2xl font-bold text-primary">{{ statistics.team_members || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Team Members</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 dark:bg-green-950 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ statistics.customers || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Customers</div>
                        </div>
                        <div class="text-center p-4 bg-blue-50 dark:bg-blue-950 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ statistics.conversations || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Conversations</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-950 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ statistics.tickets || 0 }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">Tickets</div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- ============================================ -->
            <!-- SUBMIT BUTTON -->
            <!-- ============================================ -->
            <div class="flex justify-end gap-3 pt-4">
                <Button type="button" label="Reset" severity="secondary" outlined @click="resetForm" />
                <Button type="submit" :label="saving ? 'Saving...' : 'Save Changes'" severity="primary"
                    :loading="saving" :disabled="saving" icon="pi pi-save" />
            </div>
        </form>
    </div>
</template>


<style scoped>
/* Custom styles for PrimeVue components */
:deep(.p-card) {
    background: var(--surface-card);
    border-radius: 12px;
    border: 1px solid var(--surface-border);
}

:deep(.p-card .p-card-title) {
    padding: 1.25rem 1.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
}

:deep(.p-card .p-card-content) {
    padding: 1.25rem;
}

:deep(.p-inputtext) {
    width: 100%;
}

:deep(.p-select) {
    width: 100%;
}

:deep(.p-select .p-select-label) {
    width: 100%;
}

/* Dark mode support */
:deep(.dark .p-card) {
    background: var(--surface-card-dark);
    border-color: var(--surface-border-dark);
}

/* Icon spacing */
.pi {
    font-size: 1.2rem;
}

/* Logo preview hover effect */
.logo-preview:hover {
    opacity: 0.8;
    cursor: pointer;
}
</style>
