<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import PlatformDashboard from '@/views/admin/PlatformDashboard.vue';
import TenantDashboard from '@/components/dashboard/TenantDashboard.vue';

const auth = useAuthStore();
const isPlatformScope = computed(() => auth.scope === 'platform');
</script>

<template>
    <PlatformDashboard v-if="isPlatformScope" />
    <TenantDashboard v-else />
</template>
