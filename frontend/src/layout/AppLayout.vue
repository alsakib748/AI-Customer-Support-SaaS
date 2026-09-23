<script setup>
import { useLayout } from '@/layout/composables/layout';
import { computed } from 'vue';
import AppFooter from './AppFooter.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';

import UpgradePromptDialog from '@/components/billing/UpgradePromptDialog.vue';
import { usePlanLimit } from '@/utils/planLimit';

const planLimit = usePlanLimit();

const { layoutConfig, layoutState, hideMobileMenu } = useLayout();

const containerClass = computed(() => {
    return {
        'layout-overlay': layoutConfig.menuMode === 'overlay',
        'layout-static': layoutConfig.menuMode === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive,
        'layout-mobile-active': layoutState.mobileMenuActive,
        'layout-static-inactive': layoutState.staticMenuInactive
    };
});
</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <AppTopbar />
        <AppSidebar />
        <div class="layout-main-container">
            <div class="layout-main">
                <router-view />
            </div>
            <AppFooter />
        </div>
        <div class="layout-mask animate-fadein" @click="hideMobileMenu" />
    </div>

    <UpgradePromptDialog v-model="planLimit.visible.value" :feature="planLimit.payload.value.feature" :limit="planLimit.payload.value.limit" :current="planLimit.payload.value.current" />

    <Toast />
</template>
