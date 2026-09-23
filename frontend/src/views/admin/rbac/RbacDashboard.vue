<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRbacStore } from '@/stores/rbac';

const rbac = useRbacStore();

// ---------------------------------------------------------
// STATE
// ---------------------------------------------------------
const selectedRoleId = ref(null);
const draftPermissions = ref([]);

const showRoleDialog = ref(false);
const showPermissionDialog = ref(false);
const showDeleteRoleDialog = ref(false);
const showDeletePermissionDialog = ref(false);

const editingRoleId = ref(null);
const editingPermissionId = ref(null);
const deletingPermission = ref(null);

const roleForm = reactive({ name: '', description: '' });
const permissionForm = reactive({ name: '', label: '' });

// ---------------------------------------------------------
// COMPUTED
// ---------------------------------------------------------
const roles = computed(() => rbac.roles);
const permissions = computed(() => rbac.permissions);
const saving = computed(() => rbac.saving);

const totalPermissions = computed(() => permissions.value.reduce((sum, g) => sum + g.permissions.length, 0));

const selectedRole = computed(() => roles.value.find((r) => r.id === selectedRoleId.value) ?? null);

const isProtected = computed(() => selectedRole.value?.name === 'super_admin');

// ---------------------------------------------------------
// ROLE ACTIONS
// ---------------------------------------------------------
const selectRole = (role) => {
    selectedRoleId.value = role.id;
    draftPermissions.value = [...(role.permissions ?? [])];
};

const isGroupFullySelected = (group) => group.permissions.every((p) => draftPermissions.value.includes(p.name));

const isGroupPartiallySelected = (group) => {
    const selected = group.permissions.filter((p) => draftPermissions.value.includes(p.name)).length;
    return selected > 0 && selected < group.permissions.length;
};

const toggleGroup = (group, value) => {
    const names = group.permissions.map((p) => p.name);
    if (value) {
        draftPermissions.value = [...new Set([...draftPermissions.value, ...names])];
    } else {
        draftPermissions.value = draftPermissions.value.filter((n) => !names.includes(n));
    }
};

const selectAll = () => {
    draftPermissions.value = rbac.flatPermissions;
};

const clearAll = () => {
    draftPermissions.value = [];
};

const savePermissions = async () => {
    if (!selectedRole.value) return;
    await rbac.syncRolePermissions(selectedRole.value.id, draftPermissions.value);
};

const openCreateRole = () => {
    editingRoleId.value = null;
    roleForm.name = '';
    roleForm.description = '';
    showRoleDialog.value = true;
};

const openEditRole = () => {
    if (!selectedRole.value) return;
    editingRoleId.value = selectedRole.value.id;
    roleForm.name = selectedRole.value.name;
    roleForm.description = selectedRole.value.description ?? '';
    showRoleDialog.value = true;
};

const submitRole = async () => {
    try {
        if (editingRoleId.value) {
            await rbac.updateRole(editingRoleId.value, roleForm);
        } else {
            await rbac.createRole(roleForm);
        }
        showRoleDialog.value = false;
    } catch (e) {
        console.error(e);
    }
};

const confirmDeleteRole = () => {
    showDeleteRoleDialog.value = true;
};

const submitDeleteRole = async () => {
    if (!selectedRole.value) return;
    try {
        await rbac.deleteRole(selectedRole.value.id);
        showDeleteRoleDialog.value = false;
        selectedRoleId.value = null;
        draftPermissions.value = [];
    } catch (e) {
        console.error(e);
    }
};

// ---------------------------------------------------------
// PERMISSION ACTIONS
// ---------------------------------------------------------
const openCreatePermission = () => {
    editingPermissionId.value = null;
    permissionForm.name = '';
    permissionForm.label = '';
    showPermissionDialog.value = true;
};

const editPermission = (p) => {
    editingPermissionId.value = p.id;
    permissionForm.name = p.name;
    permissionForm.label = p.label;
    showPermissionDialog.value = true;
};

const submitPermission = async () => {
    try {
        if (editingPermissionId.value) {
            await rbac.updatePermission(editingPermissionId.value, {
                label: permissionForm.label
            });
        } else {
            await rbac.createPermission({
                name: permissionForm.name,
                label: permissionForm.label
            });
        }
        showPermissionDialog.value = false;
    } catch (e) {
        console.error(e);
    }
};

const confirmDeletePermission = (p) => {
    deletingPermission.value = p;
    showDeletePermissionDialog.value = true;
};

const submitDeletePermission = async () => {
    if (!deletingPermission.value) return;
    try {
        await rbac.deletePermission(deletingPermission.value.id);
        showDeletePermissionDialog.value = false;
        deletingPermission.value = null;
    } catch (e) {
        console.error(e);
    }
};

// ---------------------------------------------------------
// LIFECYCLE
// ---------------------------------------------------------
onMounted(async () => {
    await Promise.all([rbac.fetchRoles(), rbac.fetchPermissions()]);
    const first = roles.value.find((r) => r.name !== 'super_admin') ?? roles.value[0];
    if (first) selectRole(first);
});

watch(roles, () => {
    if (selectedRoleId.value) {
        const updated = roles.value.find((r) => r.id === selectedRoleId.value);
        if (updated) draftPermissions.value = [...(updated.permissions ?? [])];
    }
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Roles & Permissions</h1>
            <p class="text-surface-600">Manage roles, permissions, and their associations.</p>
        </div>

        <TabView>
            <!-- ================================================= -->
            <!-- TAB 1: ROLES + PERMISSION ASSIGNMENT              -->
            <!-- ================================================= -->
            <TabPanel header="Roles" leftIcon="pi pi-users">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mt-4">
                    <!-- Roles list -->
                    <aside class="lg:col-span-1">
                        <Card>
                            <template #title>
                                <div class="flex items-center justify-between">
                                    <span>Roles ({{ roles.length }})</span>
                                    <Button icon="pi pi-plus" size="small" severity="primary" @click="openCreateRole" />
                                </div>
                            </template>
                            <template #content>
                                <ul class="space-y-1">
                                    <li
                                        v-for="r in roles"
                                        :key="r.id"
                                        class="cursor-pointer rounded-lg px-3 py-2 flex items-center justify-between transition-colors"
                                        :class="{
                                            'bg-primary text-primary-contrast': selectedRoleId === r.id,
                                            'hover:bg-surface-100 dark:hover:bg-surface-800': selectedRoleId !== r.id
                                        }"
                                        @click="selectRole(r)"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ r.label }}</span>
                                            <i v-if="r.is_system" class="pi pi-lock text-xs" :title="'System role'"></i>
                                        </div>
                                        <Tag :value="r.permissions_count" :severity="r.name === 'super_admin' ? 'danger' : 'info'" />
                                    </li>
                                </ul>
                            </template>
                        </Card>
                    </aside>

                    <!-- Permission matrix -->
                    <section class="lg:col-span-3">
                        <Card v-if="selectedRole">
                            <template #title>
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div>
                                        <span>{{ selectedRole.label }}</span>
                                        <Tag v-if="selectedRole.is_system" value="System" severity="warning" class="ml-2" />
                                        <Tag v-if="!selectedRole.is_global" value="Tenant-specific" severity="info" class="ml-2" />
                                    </div>
                                    <div class="flex gap-2 flex-wrap">
                                        <Button label="Select All" size="small" severity="secondary" outlined :disabled="isProtected" @click="selectAll" />
                                        <Button label="Clear All" size="small" severity="secondary" outlined :disabled="isProtected" @click="clearAll" />
                                        <Button label="Save" icon="pi pi-check" size="small" :loading="saving" :disabled="isProtected" @click="savePermissions" />
                                        <Button icon="pi pi-pencil" size="small" severity="secondary" outlined :disabled="selectedRole.is_system" @click="openEditRole" />
                                        <Button icon="pi pi-trash" size="small" severity="danger" outlined :disabled="selectedRole.is_system" @click="confirmDeleteRole" />
                                    </div>
                                </div>
                            </template>
                            <template #content>
                                <div v-if="isProtected" class="p-4 bg-red-50 dark:bg-red-950 rounded-lg mb-4">
                                    <i class="pi pi-info-circle text-red-600 mr-2"></i>
                                    The <strong>{{ selectedRole.name }}</strong> role bypasses all permission checks and cannot be edited.
                                </div>

                                <div v-else-if="selectedRole.description" class="mb-4 text-surface-600">
                                    {{ selectedRole.description }}
                                </div>

                                <div class="space-y-6">
                                    <div v-for="group in permissions" :key="group.module" class="border rounded-lg p-4 dark:border-surface-700">
                                        <div class="flex items-center justify-between mb-3">
                                            <h3 class="font-semibold text-lg">
                                                {{ group.label }}
                                                <span class="text-sm text-surface-500 ml-2"> ({{ group.permissions.length }}) </span>
                                            </h3>
                                            <Checkbox :modelValue="isGroupFullySelected(group)" :binary="true" :indeterminate="isGroupPartiallySelected(group)" :disabled="isProtected" @update:modelValue="toggleGroup(group, $event)" />
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div v-for="p in group.permissions" :key="p.id" class="flex items-center gap-2">
                                                <Checkbox :inputId="`perm-${p.id}`" :value="p.name" v-model="draftPermissions" :disabled="isProtected" />
                                                <label :for="`perm-${p.id}`" class="text-sm cursor-pointer select-none" :class="{ 'text-surface-400': isProtected }">
                                                    {{ p.label }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <div v-else class="text-center py-20 text-surface-500">
                            <i class="pi pi-arrow-left text-4xl mb-3"></i>
                            <p>Select a role to manage its permissions.</p>
                        </div>
                    </section>
                </div>
            </TabPanel>

            <!-- ================================================= -->
            <!-- TAB 2: PERMISSIONS CRUD                           -->
            <!-- ================================================= -->
            <TabPanel header="Permissions" leftIcon="pi pi-key">
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-surface-600">
                            Total: <strong>{{ totalPermissions }}</strong> permissions across <strong>{{ permissions.length }}</strong> modules
                        </p>
                        <Button label="New Permission" icon="pi pi-plus" size="small" @click="openCreatePermission" />
                    </div>

                    <div class="space-y-4">
                        <Card v-for="group in permissions" :key="group.module">
                            <template #title>
                                <div class="flex items-center justify-between">
                                    <span>{{ group.label }} ({{ group.permissions.length }})</span>
                                </div>
                            </template>
                            <template #content>
                                <DataTable :value="group.permissions" size="small">
                                    <Column field="name" header="Key">
                                        <template #body="{ data }">
                                            <span class="font-mono text-sm">{{ data.name }}</span>
                                        </template>
                                    </Column>
                                    <Column field="label" header="Label" />
                                    <Column field="is_system" header="Type">
                                        <template #body="{ data }">
                                            <Tag :value="data.is_system ? 'System' : 'Custom'" :severity="data.is_system ? 'warning' : 'info'" />
                                        </template>
                                    </Column>
                                    <Column header="Actions" style="width: 120px">
                                        <template #body="{ data }">
                                            <Button v-if="!data.is_system" icon="pi pi-pencil" severity="info" text rounded @click="editPermission(data)" />
                                            <Button v-if="!data.is_system" icon="pi pi-trash" severity="danger" text rounded @click="confirmDeletePermission(data)" />
                                        </template>
                                    </Column>
                                </DataTable>
                            </template>
                        </Card>
                    </div>
                </div>
            </TabPanel>
        </TabView>

        <!-- Create / Edit Role Dialog -->
        <Dialog v-model:visible="showRoleDialog" :header="editingRoleId ? 'Edit Role' : 'Create Role'" :style="{ width: '450px' }" modal>
            <div class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Role Key *</label>
                    <InputText v-model="roleForm.name" placeholder="e.g. billing_manager" :disabled="!!editingRoleId" />
                    <small class="text-surface-500"> Lowercase letters, numbers, underscores. No spaces. </small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Description</label>
                    <Textarea v-model="roleForm.description" rows="2" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showRoleDialog = false" />
                <Button :label="editingRoleId ? 'Save' : 'Create'" :loading="saving" @click="submitRole" />
            </template>
        </Dialog>

        <!-- Create / Edit Permission Dialog -->
        <Dialog v-model:visible="showPermissionDialog" :header="editingPermissionId ? 'Edit Permission' : 'Create Permission'" :style="{ width: '450px' }" modal>
            <div class="space-y-4">
                <div v-if="!editingPermissionId" class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Permission Key *</label>
                    <InputText v-model="permissionForm.name" placeholder="e.g. reports.export" />
                    <small class="text-surface-500"> Format: <code>module.action</code> </small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">Label *</label>
                    <InputText v-model="permissionForm.label" placeholder="Export Reports" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showPermissionDialog = false" />
                <Button :label="editingPermissionId ? 'Save' : 'Create'" :loading="saving" @click="submitPermission" />
            </template>
        </Dialog>

        <!-- Delete Role Confirmation -->
        <Dialog v-model:visible="showDeleteRoleDialog" header="Delete Role" :style="{ width: '400px' }" modal>
            <p>
                Are you sure you want to delete the role <strong>{{ selectedRole?.label }}</strong
                >?
            </p>
            <p class="text-sm text-surface-500 mt-2">This action cannot be undone.</p>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showDeleteRoleDialog = false" />
                <Button label="Delete" severity="danger" :loading="saving" @click="submitDeleteRole" />
            </template>
        </Dialog>

        <!-- Delete Permission Confirmation -->
        <Dialog v-model:visible="showDeletePermissionDialog" header="Delete Permission" :style="{ width: '400px' }" modal>
            <p>
                Delete permission <strong>{{ deletingPermission?.name }}</strong
                >?
            </p>
            <p class="text-sm text-surface-500 mt-2">It will be removed from all roles and users.</p>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="showDeletePermissionDialog = false" />
                <Button label="Delete" severity="danger" :loading="saving" @click="submitDeletePermission" />
            </template>
        </Dialog>

        <Toast />
    </div>
</template>
