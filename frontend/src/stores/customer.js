import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import customerService from '@/services/customerService';
import { toast } from 'vue3-toastify';

export const useCustomerStore = defineStore('customer', () => {
    //todo; ================= STATE =====================
    const customers = ref([]);
    const currentCustomer = ref(null);
    const statistics = ref({
        total: 0,
        active: 0,
        inactive: 0,
        blocked: 0,
        new_this_week: 0,
        new_this_month: 0,
        with_conversations: 0
    });
    const tags = ref([]);
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        status: null,
        tag: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    //todo; ================= GETTERS =====================
    const totalCustomers = computed(() => pagination.value?.total || 0);
    const hasCustomers = computed(() => customers.value.length > 0);
    const activeCount = computed(() => statistics.value?.active || 0);
    const blockedCount = computed(() => statistics.value?.blocked || 0);

    //todo; =============== ACTIONS - CRUD ================
    const fetchCustomers = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await customerService.getCustomers(mergedParams);

            if (response.data.success) {
                customers.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch customers:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load customers'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load customers');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchCustomer = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await customerService.getCustomer(id);

            if (response.data.success) {
                currentCustomer.value = response.data.data;
                return currentCustomer.value;
            }
        } catch (error) {
            console.error('Failed to fetch customer:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load customer'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load customer');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const createCustomer = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await customerService.createCustomer(data);

            if (response.data.success) {
                toast.success(response.data.message || 'Customer created successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to create customer:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to create customer'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to create customer');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const updateCustomer = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await customerService.updateCustomer(id, data);

            if (response.data.success) {
                // Update in list
                const index = customers.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    customers.value[index] = response.data.data;
                }

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = response.data.data;
                }

                toast.success(response.data.message || 'Customer updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update customer:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update customer'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update customer');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const deleteCustomer = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await customerService.deleteCustomer(id);

            if (response.data.success) {
                // Remove from list
                customers.value = customers.value.filter((c) => c.id !== id);

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = null;
                }

                toast.success(response.data.message || 'Customer deleted successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to delete customer:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to delete customer'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to delete customer');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ACTIONS - BLOCK/UNBLOCK
    // ============================================

    const blockCustomer = async (id, reason = null) => {
        saving.value = true;

        try {
            const response = await customerService.blockCustomer(id, reason);

            if (response.data.success) {
                // Update in list
                const index = customers.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    customers.value[index] = response.data.data;
                }

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = response.data.data;
                }

                toast.success(response.data.message || 'Customer blocked successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to block customer:', error);
            toast.error(error.response?.data?.message || 'Failed to block customer');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const unblockCustomer = async (id) => {
        saving.value = true;

        try {
            const response = await customerService.unblockCustomer(id);

            if (response.data.success) {
                // Update in list
                const index = customers.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    customers.value[index] = response.data.data;
                }

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = response.data.data;
                }

                toast.success(response.data.message || 'Customer unblocked successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to unblock customer:', error);
            toast.error(error.response?.data?.message || 'Failed to unblock customer');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ACTIONS - TAGS
    // ============================================

    const addTag = async (id, tag) => {
        saving.value = true;

        try {
            const response = await customerService.addTag(id, tag);

            if (response.data.success) {
                // Update in list
                const index = customers.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    customers.value[index] = response.data.data;
                }

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = response.data.data;
                }

                toast.success(response.data.message || 'Tag added successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to add tag:', error);
            toast.error(error.response?.data?.message || 'Failed to add tag');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const removeTag = async (id, tag) => {
        saving.value = true;

        try {
            const response = await customerService.removeTag(id, tag);

            if (response.data.success) {
                // Update in list
                const index = customers.value.findIndex((c) => c.id === id);
                if (index !== -1) {
                    customers.value[index] = response.data.data;
                }

                if (currentCustomer.value?.id === id) {
                    currentCustomer.value = response.data.data;
                }

                toast.success(response.data.message || 'Tag removed successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to remove tag:', error);
            toast.error(error.response?.data?.message || 'Failed to remove tag');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // ACTIONS - STATISTICS & TAGS
    // ============================================

    const fetchStatistics = async () => {
        try {
            const response = await customerService.getStatistics();

            // console.log('response: ' + response.data);

            if (response.data.success) {
                statistics.value = response.data.data || statistics.value;
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch statistics:', error);
            toast.error('Failed to load statistics');
            return null;
        }
    };

    const fetchTags = async () => {
        try {
            const response = await customerService.getTags();

            if (response.data.success) {
                tags.value = response.data.data || [];
                return tags.value;
            }
        } catch (error) {
            console.error('Failed to fetch tags:', error);
            toast.error('Failed to load tags');
            return [];
        }
    };

    // ============================================
    // UTILITY ACTIONS
    // ============================================

    const resetFilters = () => {
        filters.value = {
            search: '',
            status: null,
            tag: null,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    const clearErrors = () => {
        errors.value = {};
    };

    const getFieldError = (field) => {
        if (errors.value && errors.value[field]) {
            return Array.isArray(errors.value[field]) ? errors.value[field][0] : errors.value[field];
        }
        return null;
    };

    // todo; ==================== RETURN =====================

    return {
        // State
        customers,
        currentCustomer,
        statistics,
        tags,
        pagination,
        loading,
        saving,
        errors,
        filters,

        // Getters
        totalCustomers,
        hasCustomers,
        activeCount,
        blockedCount,

        // CRUD Actions
        fetchCustomers,
        fetchCustomer,
        createCustomer,
        updateCustomer,
        deleteCustomer,

        // Block/Unblock
        blockCustomer,
        unblockCustomer,

        // Tags
        addTag,
        removeTag,

        // Statistics & Tags
        fetchStatistics,
        fetchTags,

        // Utility
        resetFilters,
        clearErrors,
        getFieldError
    };
});
