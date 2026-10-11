<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '@/stores/notification';
import NotificationDropdown from './NotificationDropdown.vue';

const router = useRouter();
const store = useNotificationStore();

const open = ref(false);
const root = ref(null);

const toggle = async () => {
    open.value = !open.value;
    if (open.value) {
        await store.fetchPage(1).catch(() => {});
    }
};

const close = () => {
    open.value = false;
};

const onDocumentClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
};

const openNotification = async (notification) => {
    if (!notification.read_at) {
        store.markRead(notification.id).catch(() => {});
    }
    close();
    if (notification.action_url) {
        router.push(notification.action_url);
    }
};

const markAllRead = () => store.markAllRead().catch(() => {});
const markRead = (notification) => store.markRead(notification.id).catch(() => {});
const remove = (notification) => store.remove(notification.id).catch(() => {});

onMounted(() => {
    store.fetchUnreadCount();
    store.startPolling(30000);
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    store.stopPolling();
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="layout-topbar-action relative" title="Notifications" @click="toggle">
            <i class="pi pi-bell"></i>
            <span v-if="store.hasUnread" class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 top-12 z-50">
            <NotificationDropdown :items="store.items" :loading="store.loading" :managing="store.managing" :unread-count="store.unreadCount" @read="markRead" @remove="remove" @open="openNotification" @mark-all-read="markAllRead" @view-all="close" />
        </div>
    </div>
</template>
