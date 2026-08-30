import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import workspaceService from '@/services/workspaceService';
import { toast } from 'vue3-toastify';

export const useWorkspaceStore = defineStore('workspace', () => {
    // State
    const workspace = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});
    const statistics = ref(null);

    // Getters
    const workspaceName = computed(() => workspace.value?.name || '');
    const workspaceLogo = computed(() => workspace.value?.logo || null);
    const workspaceIndustry = computed(() => workspace.value?.industry || '');
    const workspaceTimezone = computed(() => workspace.value?.timezone || 'UTC');
    const workspaceLanguage = computed(() => workspace.value?.default_language || 'en');
    const workspaceSupportEmail = computed(() => workspace.value?.support_email || '');
    const workspaceSupportPhone = computed(() => workspace.value?.support_phone || '');

    // Actions
    const fetchWorkspace = async () => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await workspaceService.getWorkspace();

            if (response.data.success) {
                workspace.value = response.data.data;
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to fetch workspace: ', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error(error.response?.data?.message || 'Failed to load workspace');

            throw error;
        } finally {
            loading.value = false;
        }
    };

    const updateWorkspace = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await workspaceService.updateWorkspace(data);

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success(response.data.message || 'Workspace updated successfully');

                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to update workspace:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error(error.response?.data?.message || 'Failed to update workspace');

            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateLogo = async (file) => {
        try {
            const response = await workspaceService.updateLogo(file);

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success('Logo updated successfully');
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to update logo:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error(error.response?.data?.message || 'Failed to update logo');
            throw error;
        }
    };

    const deleteLogo = async () => {
        try {
            const response = await workspaceService.deleteLogo();

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success('Logo deleted successfully');
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to delete logo:', error);
            toast.error(error.response?.data?.message || 'Failed to delete logo');
            throw error;
        }
    };
    const updateFavicon = async (file) => {
        try {
            const response = await workspaceService.updateFavicon(file);

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success('Favicon updated successfully');
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to update favicon:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error(error.response?.data?.message || 'Failed to update favicon');
            throw error;
        }
    };

    const deleteFavicon = async () => {
        try {
            const response = await workspaceService.deleteFavicon();

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success('Favicon deleted successfully');
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to delete favicon:', error);
            toast.error(error.response?.data?.message || 'Failed to delete favicon');
            throw error;
        }
    };

    const fetchStatistics = async () => {
        try {
            const response = await workspaceService.getStatistics();

            if (response.data.success) {
                statistics.value = response.data.data;
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch statistics:', error);
            toast.error(error.response?.data?.message || 'Failed to load statistics');
            throw error;
        }
    };

    const updateBusinessHours = async (businessHours) => {
        try {
            const response = await workspaceService.updateBusinessHours(businessHours);

            if (response.data.success) {
                workspace.value = response.data.data;
                toast.success('Business hours updated successfully');
                return workspace.value;
            }
        } catch (error) {
            console.error('Failed to update business hours:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }

            toast.error(error.response?.data?.message || 'Failed to update business hours');
            throw error;
        }
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    return {
        // state
        workspace,
        loading,
        saving,
        errors,
        statistics,

        // Getters
        workspaceName,
        workspaceLogo,
        workspaceIndustry,
        workspaceTimezone,
        workspaceLanguage,
        workspaceSupportEmail,
        workspaceSupportPhone,

        // Actions
        fetchWorkspace,
        updateWorkspace,
        updateLogo,
        deleteLogo,
        updateFavicon,
        deleteFavicon,
        fetchStatistics,
        updateBusinessHours,
        getFieldError
    };
});
