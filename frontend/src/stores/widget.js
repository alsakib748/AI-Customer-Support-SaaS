// src/stores/widget.js

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import widgetService from '@/services/widgetService';
import { toast } from 'vue3-toastify';

export const useWidgetStore = defineStore('widget', () => {
    // ============================================
    // STATE
    // ============================================

    const widgets = ref([]);
    const currentWidget = ref(null);
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        status: null,
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================

    const totalWidgets = computed(() => pagination.value?.total || 0);
    const hasWidgets = computed(() => widgets.value.length > 0);
    const activeWidgets = computed(() => widgets.value.filter((w) => w.status === 'active').length);
    const disabledWidgets = computed(() => widgets.value.filter((w) => w.status === 'disabled').length);

    // ============================================
    // ACTIONS
    // ============================================

    const fetchWidgets = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await widgetService.getWidgets(mergedParams);

            if (response.data.success) {
                widgets.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch widgets:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load widgets'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load widgets');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchWidget = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await widgetService.getWidget(id);

            if (response.data.success) {
                currentWidget.value = response.data.data;
                return currentWidget.value;
            }
        } catch (error) {
            console.error('Failed to fetch widget:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load widget'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load widget');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createWidget = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await widgetService.createWidget(data);

            if (response.data.success) {
                // Add to list
                widgets.value.unshift(response.data.data);
                toast.success(response.data.message || 'Widget created successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create widget:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to create widget'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create widget');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateWidget = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await widgetService.updateWidget(id, data);

            if (response.data.success) {
                // Update in list
                const index = widgets.value.findIndex((w) => w.id === id);
                if (index !== -1) {
                    widgets.value[index] = response.data.data;
                }

                if (currentWidget.value?.id === id) {
                    currentWidget.value = response.data.data;
                }

                toast.success(response.data.message || 'Widget updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update widget:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update widget'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update widget');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteWidget = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await widgetService.deleteWidget(id);

            if (response.data.success) {
                widgets.value = widgets.value.filter((w) => w.id !== id);

                if (currentWidget.value?.id === id) {
                    currentWidget.value = null;
                }

                toast.success(response.data.message || 'Widget deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete widget:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete widget'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete widget');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const enableWidget = async (id) => {
        saving.value = true;

        try {
            const response = await widgetService.enableWidget(id);

            if (response.data.success) {
                // Update in list
                const index = widgets.value.findIndex((w) => w.id === id);
                if (index !== -1) {
                    widgets.value[index] = response.data.data;
                }

                if (currentWidget.value?.id === id) {
                    currentWidget.value = response.data.data;
                }

                toast.success(response.data.message || 'Widget enabled successfully ✅');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to enable widget:', error);
            toast.error(error.response?.data?.message || 'Failed to enable widget');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const disableWidget = async (id) => {
        saving.value = true;

        try {
            const response = await widgetService.disableWidget(id);

            if (response.data.success) {
                const index = widgets.value.findIndex((w) => w.id === id);
                if (index !== -1) {
                    widgets.value[index] = response.data.data;
                }

                if (currentWidget.value?.id === id) {
                    currentWidget.value = response.data.data;
                }

                toast.success(response.data.message || 'Widget disabled successfully 🔒');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to disable widget:', error);
            toast.error(error.response?.data?.message || 'Failed to disable widget');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const regenerateKey = async (id) => {
        saving.value = true;

        try {
            const response = await widgetService.regenerateKey(id);

            if (response.data.success) {
                const index = widgets.value.findIndex((w) => w.id === id);
                if (index !== -1) {
                    widgets.value[index] = response.data.data;
                }

                if (currentWidget.value?.id === id) {
                    currentWidget.value = response.data.data;
                }

                toast.success(response.data.message || 'Key regenerated successfully 🔑');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to regenerate key:', error);
            toast.error(error.response?.data?.message || 'Failed to regenerate key');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const getInstallationCode = async (id) => {
        try {
            const response = await widgetService.getInstallationCode(id);

            if (response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.error('Failed to get installation code:', error);
            toast.error(error.response?.data?.message || 'Failed to get installation code');
            throw error;
        }
    };

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            per_page: 20
        };
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    // ============================================
    // RETURN
    // ============================================

    return {
        // State
        widgets,
        currentWidget,
        pagination,
        loading,
        saving,
        errors,
        filters,

        // Getters
        totalWidgets,
        hasWidgets,
        activeWidgets,
        disabledWidgets,

        // Actions
        fetchWidgets,
        fetchWidget,
        createWidget,
        updateWidget,
        deleteWidget,
        enableWidget,
        disableWidget,
        regenerateKey,
        getInstallationCode,
        resetFilters,
        getFieldError
    };
});
