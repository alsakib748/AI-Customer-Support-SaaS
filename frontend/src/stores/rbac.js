import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import rbacService from '@/services/rbacService';
import { toast } from 'vue3-toastify';

export const useRbacStore = defineStore('rbac', () => {
    // ---------------------------------------------------------
    // STATE
    // ---------------------------------------------------------
    const roles = ref([]);
    const permissions = ref([]); // grouped
    const meta = ref({});
    const loading = ref(false);
    const saving = ref(false);

    // ---------------------------------------------------------
    // GETTERS
    // ---------------------------------------------------------
    const flatPermissions = computed(() => permissions.value.flatMap((g) => g.permissions.map((p) => p.name)));

    const permissionMap = computed(() => {
        const map = {};
        permissions.value.forEach((group) => {
            group.permissions.forEach((p) => {
                map[p.name] = p;
            });
        });
        return map;
    });

    const systemRoles = computed(() => roles.value.filter((r) => r.is_system));
    const customRoles = computed(() => roles.value.filter((r) => !r.is_system));

    // ---------------------------------------------------------
    // ACTIONS
    // ---------------------------------------------------------
    const fetchRoles = async () => {
        loading.value = true;
        try {
            const { data } = await rbacService.listRoles();
            if (data.success) {
                roles.value = data.data;
                meta.value = data.meta ?? {};
            }
        } catch (e) {
            console.error('fetchRoles:', e);
            toast.error('Failed to load roles');
        } finally {
            loading.value = false;
        }
    };

    const fetchPermissions = async () => {
        loading.value = true;
        try {
            const { data } = await rbacService.listPermissions();
            if (data.success) permissions.value = data.data;
        } catch (e) {
            console.error('fetchPermissions:', e);
            toast.error('Failed to load permissions');
        } finally {
            loading.value = false;
        }
    };

    const createRole = async (payload) => {
        saving.value = true;
        try {
            const { data } = await rbacService.createRole(payload);
            toast.success('Role created');
            await fetchRoles();
            return data.data;
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to create role');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const updateRole = async (id, payload) => {
        saving.value = true;
        try {
            await rbacService.updateRole(id, payload);
            toast.success('Role updated');
            await fetchRoles();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to update role');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const deleteRole = async (id) => {
        saving.value = true;
        try {
            await rbacService.deleteRole(id);
            toast.success('Role deleted');
            await fetchRoles();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to delete role');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const syncRolePermissions = async (roleId, permissionNames) => {
        saving.value = true;
        try {
            const { data } = await rbacService.syncRolePermissions(roleId, permissionNames);
            if (data.success) {
                const idx = roles.value.findIndex((r) => r.id === roleId);
                if (idx !== -1) {
                    roles.value[idx].permissions = data.data.permissions;
                    roles.value[idx].permissions_count = data.data.permissions.length;
                }
                toast.success('Permissions saved');
            }
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to save');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const createPermission = async (payload) => {
        saving.value = true;
        try {
            await rbacService.createPermission(payload);
            toast.success('Permission created');
            await fetchPermissions();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to create');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const updatePermission = async (id, payload) => {
        saving.value = true;
        try {
            await rbacService.updatePermission(id, payload);
            toast.success('Permission updated');
            await fetchPermissions();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to update');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    const deletePermission = async (id) => {
        saving.value = true;
        try {
            await rbacService.deletePermission(id);
            toast.success('Permission deleted');
            await fetchPermissions();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to delete');
            throw e;
        } finally {
            saving.value = false;
        }
    };

    return {
        roles,
        permissions,
        meta,
        loading,
        saving,
        flatPermissions,
        permissionMap,
        systemRoles,
        customRoles,
        fetchRoles,
        fetchPermissions,
        createRole,
        updateRole,
        deleteRole,
        syncRolePermissions,
        createPermission,
        updatePermission,
        deletePermission
    };
});
