<script setup>
import { computed, ref } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import AppConfigurator from './AppConfigurator.vue';
import { useAuthStore } from '@/stores/auth.js';
import NotificationBell from '@/components/admin/notifications/NotificationBell.vue';
import { toast } from 'vue3-toastify';
import { useRouter } from 'vue-router';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();

const authStore = useAuthStore();
const router = useRouter();

const canViewNotifications = computed(() => authStore.isSuperAdmin && authStore.hasPermission('platform.notifications'));

const userAvatar = computed(() => authStore.user?.avatar_url || null);
const userInitial = computed(() => (authStore.user?.full_name || authStore.user?.email || 'U').charAt(0).toUpperCase());

const profileModal = ref(false);

const profileView = () => {
    profileModal.value = !profileModal.value;
};

const Logout = async () => {
    try {
        await authStore.logout();
        router.push('/login');
        toast.success('User logout successfully');
    } catch (e) {
        console.error('Login failed', e);
    }
};
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo">
                <img src="/SupportAI-brand-assets/svg/symbol.svg" alt="SupportAI logo" class="h-8 w-auto" />
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <NotificationBell v-if="canViewNotifications" />

            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>
                    <AppConfigurator />
                </div>
            </div>

            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden relative lg:block">
                <div class="layout-topbar-menu-content">
                    <!-- <button type="button" class="layout-topbar-action">
                        <i class="pi pi-calendar"></i>
                        <span>Calendar</span>
                    </button>
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-inbox"></i>
                        <span>Messages</span>
                    </button> -->
                    <button @click="profileView" type="button" class="layout-topbar-action">
                        <Avatar v-if="userAvatar" :image="userAvatar" :label="userAvatar ? undefined : userInitial" shape="circle" class="!w-6 !h-6 !text-xs" />
                        <i v-else class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                </div>

                <div v-if="profileModal" class="max-w-sm rounded-md overflow-hidden shadow-lg absolute right-0 sm:top-24 md:top-13 bg-white">
                    <router-link to="/profile" class="" @click="profileModal = false">
                        <div class="w-40 py-3 px-3 flex items-center gap-2 hover:bg-teal-500 hover:text-white cursor-pointer dark:text-slate-800">
                            <Avatar v-if="userAvatar" :image="userAvatar" shape="circle" class="!w-7 !h-7" />
                            <i v-else class="pi pi-user"></i>
                            <span>My Profile</span>
                        </div>
                    </router-link>
                    <form @submit.prevent="Logout" method="POST">
                        <button type="submit" class="">
                            <div class="w-40 py-3 text-center hover:bg-teal-500 hover:text-white cursor-pointer dark:text-slate-800">
                                <i class="pi pi-sign-out"></i>
                                <span class="ms-2">Log Out</span>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
