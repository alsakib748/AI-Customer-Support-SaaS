<script setup>
import { onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRoute } from 'vue-router';

const route = useRoute();
const authStore = useAuthStore();

onMounted(async () => {
    // Initialize auth state
    await authStore.init();
});

// Watch for authentication changes
watch(
    () => authStore.isAuthenticated,
    (isAuthenticated) => {
        if (!isAuthenticated && route.meta.requiresAuth) {
            authStore.setRedirectPath(route.fullPath);
        }
    }
);
</script>

<template>
    <router-view />
</template>

<!-- <style scoped>
.spinner-border {
    border-color: #4f46e5;
    border-top-color: transparent;
    animation: spin 0.75s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}
</style> -->
