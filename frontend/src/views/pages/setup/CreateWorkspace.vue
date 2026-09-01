<script setup>
import { ref, reactive, computed } from 'vue';
import { useWorkspaceStore } from '@/stores/workspace';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const workspaceStore = useWorkspaceStore();
const authStore = useAuthStore();
const router = useRouter();

// State
const saving = computed(() => workspaceStore.saving);

// Form data
const form = reactive({
    name: '',
    industry: '',
    timezone: 'UTC',
    support_email: '',
    support_phone: '',
    default_language: 'en',
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

const handleSubmit = async () => {
    try {
        const response = await workspaceStore.createWorkspace({
            name: form.name,
            industry: form.industry,
            timezone: form.timezone,
            support_email: form.support_email,
            support_phone: form.support_phone,
            default_language: form.default_language,
        });

        if (response.success) {
            // Update auth store with new tenant
            authStore.currentTenant = response.data;
            localStorage.setItem('current_tenant_id', response.data.id);

            router.push('/dashboard');
        }
    } catch (error) {
        console.error('Failed to create workspace:', error);
    }
};

const getError = (field) => {
    return workspaceStore.getFieldError(field);
};
</script>

<template>
    <div class="max-w-4xl mx-auto p-6">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">
                Create Your Workspace
            </h1>
            <p class="text-surface-600 dark:text-surface-400 mt-2">
                Let's set up your AI Customer Support environment
            </p>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-building text-primary"></i>
                        <span>Workspace Information</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Workspace Name <span class="text-red-500">*</span>
                            </label>
                            <InputText v-model="form.name" class="w-full"
                                :class="{ 'p-invalid': getError('name') }"
                                placeholder="Enter workspace name" />
                            <small v-if="getError('name')" class="text-red-500 block mt-1">
                                {{ getError('name') }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Industry
                            </label>
                            <Select v-model="form.industry" :options="industries" optionLabel="label"
                                optionValue="value" placeholder="Select Industry" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Timezone <span class="text-red-500">*</span>
                            </label>
                            <Select v-model="form.timezone" :options="timezones" optionLabel="label" optionValue="value"
                                placeholder="Select Timezone" class="w-full"
                                :class="{ 'p-invalid': getError('timezone') }" />
                            <small v-if="getError('timezone')" class="text-red-500 block mt-1">
                                {{ getError('timezone') }}
                            </small>
                        </div>
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-phone text-primary"></i>
                        <span>Contact Information</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Support Email
                            </label>
                            <InputText v-model="form.support_email" type="email" class="w-full"
                                :class="{ 'p-invalid': getError('support_email') }"
                                placeholder="support@example.com" />
                            <small v-if="getError('support_email')" class="text-red-500 block mt-1">
                                {{ getError('support_email') }}
                            </small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Support Phone
                            </label>
                            <InputText v-model="form.support_phone" type="tel" class="w-full"
                                placeholder="+1 234 567 8900" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                                Default Language <span class="text-red-500">*</span>
                            </label>
                            <Select v-model="form.default_language" :options="languages" optionLabel="label"
                                optionValue="value" placeholder="Select Language" class="w-full"
                                :class="{ 'p-invalid': getError('default_language') }" />
                            <small v-if="getError('default_language')" class="text-red-500 block mt-1">
                                {{ getError('default_language') }}
                            </small>
                        </div>
                    </div>
                </template>
            </Card>

            <div class="flex justify-end gap-3 pt-4">
                <Button type="submit" :label="saving ? 'Creating...' : 'Create Workspace'" severity="primary"
                    :loading="saving" :disabled="saving" icon="pi pi-save" />
            </div>
        </form>
    </div>
</template>
