<script setup>
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

const handleLogout = () => {
    auth.logout();
    router.push('/login');
};
</script>

<template>
    <div class="flex flex-col items-center justify-center min-h-screen p-8 text-center">
        <i class="pi pi-lock text-6xl text-red-400 mb-6"></i>
        <h1 class="text-4xl font-bold mb-3">403 — Access Denied</h1>
        <p class="text-surface-600 mb-8 max-w-md">You don't have permission to view this page. If you think this is a mistake, contact your workspace owner.</p>
        <div class="flex gap-3">
            <Button label="Back to Dashboard" icon="pi pi-home" @click="$router.push('/dashboard')" />
            <Button label="Sign Out" severity="secondary" outlined @click="handleLogout" />
        </div>
    </div>
</template>
