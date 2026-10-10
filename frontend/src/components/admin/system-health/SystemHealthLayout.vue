<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();

const tabs = [
    { label: 'Dashboard', to: '/admin/system-health', icon: 'pi pi-heart-pulse', exact: true },
    { label: 'Checks', to: '/admin/system-health/checks', icon: 'pi pi-clipboard-check' },
    { label: 'Metrics', to: '/admin/system-health/metrics', icon: 'pi pi-chart-line' }
];

const visibleTabs = computed(() => tabs.filter(() => auth.isSuperAdmin || auth.hasPermission('platform.system_health.view')));

const isActive = (tab) => (tab.exact ? route.path === tab.to : route.path.startsWith(tab.to));
</script>

<template>
    <div class="max-w-6xl mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">System Health</h1>
            <p class="text-surface-600 dark:text-surface-400 mt-2">Platform-wide operational monitoring for every workspace.</p>
        </div>

        <div class="flex flex-wrap gap-2 mb-8 border-b border-surface-200 dark:border-surface-700 pb-2">
            <RouterLink
                v-for="tab in visibleTabs"
                :key="tab.to"
                :to="tab.to"
                class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                :class="isActive(tab) ? 'bg-primary text-primary-contrast' : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'"
            >
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
            </RouterLink>
        </div>

        <RouterView />
    </div>
</template>
