<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const user = computed(() => auth.user || {});
const scope = computed(() => auth.scope);
const role = computed(() => auth.role);

const details = computed(() => [
    { label: 'Full Name', value: user.value.full_name || '—' },
    { label: 'Email', value: user.value.email || '—' },
    { label: 'Phone', value: user.value.phone || '—' },
    { label: 'Scope', value: scope.value || '—' },
    { label: 'Role', value: role.value || '—' },
    { label: 'Timezone', value: user.value.timezone || 'UTC' },
    { label: 'Last Login', value: user.value.last_login_at ? new Date(user.value.last_login_at).toLocaleString() : '—' }
]);
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">My Profile</h1>
            <p class="text-surface-600 dark:text-surface-400">Super Admin account details</p>
        </div>

        <Card>
            <template #content>
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-16 h-16 rounded-full bg-primary flex items-center justify-center text-2xl font-bold text-white">
                        {{ (user.full_name || user.email || 'S')[0].toUpperCase() }}
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-surface-900 dark:text-surface-0">{{ user.full_name || user.email }}</div>
                        <div class="text-sm text-primary">{{ role }} · {{ scope }} scope</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="d in details" :key="d.label" class="flex justify-between border-b border-surface-200 dark:border-surface-700 py-2">
                        <span class="text-surface-600 dark:text-surface-400">{{ d.label }}</span>
                        <span class="font-medium text-surface-900 dark:text-surface-0">{{ d.value }}</span>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-semibold mb-2 text-surface-900 dark:text-surface-0">Permissions</h3>
                    <div class="flex flex-wrap gap-2">
                        <Tag v-for="p in auth.permissions" :key="p" :value="p" severity="secondary" />
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>
