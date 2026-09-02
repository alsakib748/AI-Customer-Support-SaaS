import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import teamService from '@/services/teamService';
import { toast } from 'vue3-toastify';

export const useTeamStore = defineStore('team', () => {
    // ============================================
    // STATE
    // ============================================
    const tenants = ref([]);
    const selectedTenantId = ref(null);

    const members = ref([]);
    const currentMember = ref(null);
    const invitations = ref([]);
    const statistics = ref({
        total: 0,
        online: 0,
        away: 0,
        offline: 0,
        busy: 0,
        by_role: {},
        by_department: {}
    });
    const departments = ref([]);
    const pagination = ref(null);
    const loading = ref(false);
    const saving = ref(false);
    const deleting = ref(false);
    const errors = ref({});

    const filters = ref({
        search: '',
        department: null,
        availability_status: null,
        role: null,
        sort: 'created_at',
        direction: 'desc',
        per_page: 20
    });

    const invitationFilters = ref({
        search: '',
        status: 'pending',
        per_page: 20
    });

    // ============================================
    // GETTERS
    // ============================================
    const totalMembers = computed(() => pagination.value?.total || 0);
    const onlineMembers = computed(() => statistics.value?.online || 0);
    const availableMembers = computed(() => (statistics.value?.online || 0) + (statistics.value?.away || 0));
    const offlineMembers = computed(() => statistics.value?.offline || 0);
    const busyMembers = computed(() => statistics.value?.busy || 0);
    const hasMembers = computed(() => members.value.length > 0);

    const totalInvitations = computed(() => pagination.value?.total || 0);
    const pendingInvitations = computed(() => {
        return invitations.value.filter((inv) => inv.is_pending).length;
    });

    const canManageTeam = computed(() => {
        // This will be set by the backend, but we can also check permissions
        return true; // Will be refined later
    });
    // ============================================
    // ACTIONS - MEMBERS
    // ============================================
    const fetchMembers = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...filters.value, ...params };

            // If Super Admin and tenant filter is selected
            if (selectedTenantId.value) {
                mergedParams.tenant_id = selectedTenantId.value;
            }

            // Clean params
            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await teamService.getMembers(mergedParams);

            if (response.data.success) {
                members.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            } else {
                throw new Error(response.data.message || 'Failed to fetch members');
            }
        } catch (error) {
            console.error('Failed to fetch members:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load team members'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load team members');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const fetchTenants = async () => {
        try {
            const response = await teamService.getTenants();

            if (response.data.success) {
                tenants.value = response.data.data || [];
                return tenants.value;
            }
        } catch (error) {
            console.error('Failed to fetch tenants:', error);
            toast.error('Failed to load tenants');
            return [];
        }
    };

    const fetchMember = async (id) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await teamService.getMember(id);

            if (response.data.success) {
                currentMember.value = response.data.data;
                return currentMember.value;
            }
        } catch (error) {
            console.error('Failed to fetch member:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load member details'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load member details');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const updateMember = async (id, data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await teamService.updateMember(id, data);

            console.log('update team members: ', response);

            if (response.data.success) {
                // Update the member in the list
                const index = members.value.findIndex((m) => m.id === id);
                if (index !== -1) {
                    members.value[index] = response.data.data;
                }

                // Update current member if it's the same
                if (currentMember.value?.id === id) {
                    currentMember.value = response.data.data;
                }

                toast.success(response.data.message || 'Member updated successfully 🎉');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to update member:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to update member'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to update member');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const removeMember = async (id) => {
        deleting.value = true;
        errors.value = {};

        try {
            const response = await teamService.removeMember(id);

            if (response.data.success) {
                // Remove from list
                members.value = members.value.filter((m) => m.id !== id);

                toast.success(response.data.message || 'Member removed successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to remove member:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to remove member'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to remove member');
            throw error;
        } finally {
            deleting.value = false;
        }
    };

    const fetchStatistics = async () => {
        try {
            const response = await teamService.getStatistics();

            // console.log(response.data);

            if (response.data.success) {
                statistics.value = response.data.data || statistics.value;
                // console.log(statistics.value);
                return statistics.value;
            }
        } catch (error) {
            console.error('Failed to fetch statistics:', error);
            // Don't throw - statistics are non-critical
            toast.error('Failed to load team statistics');
            return null;
        }
    };

    const fetchDepartments = async () => {
        try {
            const response = await teamService.getDepartments();

            // console.log('departments: ', response);

            if (response.data.success) {
                departments.value = response.data.data || [];

                return departments.value;
            }
        } catch (error) {
            console.error('Failed to fetch departments:', error);
            // Don't throw - departments are non-critical
            toast.error('Failed to load departments');
            return [];
        }
    };

    // ============================================
    // ACTIONS - INVITATIONS
    // ============================================

    const fetchInvitations = async (params = {}) => {
        loading.value = true;
        errors.value = {};

        try {
            const mergedParams = { ...invitationFilters.value, ...params };

            Object.keys(mergedParams).forEach((key) => {
                if (mergedParams[key] === null || mergedParams[key] === undefined) {
                    delete mergedParams[key];
                }
            });

            const response = await teamService.getInvitations(mergedParams);

            if (response.data.success) {
                invitations.value = response.data.data || [];
                pagination.value = response.data.meta || null;
                return response.data;
            }
        } catch (error) {
            console.error('Failed to fetch invitations:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to load invitations'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to load invitations');
            throw error;
        } finally {
            loading.value = false;
        }
    };

    const sendInvitation = async (data) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await teamService.sendInvitation(data);

            if (response.data.success) {
                toast.success(response.data.message || 'Invitation sent successfully 📧');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to send invitation:', error);

            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to send invitation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to send invitation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const resendInvitation = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await teamService.resendInvitation(id);

            if (response.data.success) {
                // Refresh invitations
                await fetchInvitations();
                toast.success(response.data.message || 'Invitation resent successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to resend invitation:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to resend invitation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to resend invitation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    const revokeInvitation = async (id) => {
        saving.value = true;
        errors.value = {};

        try {
            const response = await teamService.revokeInvitation(id);

            if (response.data.success) {
                // Refresh invitations
                await fetchInvitations();
                toast.success(response.data.message || 'Invitation revoked successfully');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to revoke invitation:', error);

            if (error.response?.data?.message) {
                errors.value = { general: [error.response.data.message] };
            } else {
                errors.value = { general: ['Failed to revoke invitation'] };
            }

            toast.error(errors.value.general?.[0] || 'Failed to revoke invitation');
            throw error;
        } finally {
            saving.value = false;
        }
    };

    // ============================================
    // UTILITY
    // ============================================

    const resetFilters = () => {
        filters.value = {
            search: '',
            department: null,
            availability_status: null,
            role: null,
            sort: 'created_at',
            direction: 'desc',
            per_page: 20
        };
    };

    const resetInvitationFilters = () => {
        invitationFilters.value = {
            search: '',
            status: 'all',
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

    return {
        // State
        tenants,
        selectedTenantId,
        members,
        currentMember,
        invitations,
        statistics,
        departments,
        pagination,
        loading,
        saving,
        deleting,
        errors,
        filters,
        invitationFilters,

        // Getters
        totalMembers,
        onlineMembers,
        availableMembers,
        offlineMembers,
        busyMembers,
        hasMembers,
        totalInvitations,
        pendingInvitations,
        canManageTeam,

        // Member Actions
        fetchMembers,
        fetchTenants,
        fetchMember,
        updateMember,
        removeMember,
        fetchStatistics,
        fetchDepartments,

        // Invitation Actions
        fetchInvitations,
        sendInvitation,
        resendInvitation,
        revokeInvitation,

        // Utility
        resetFilters,
        resetInvitationFilters,
        clearErrors,
        getFieldError
    };
});
