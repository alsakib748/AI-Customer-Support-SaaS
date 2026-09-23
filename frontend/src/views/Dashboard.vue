<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import BestSellingWidget from '@/components/dashboard/BestSellingWidget.vue';
import NotificationsWidget from '@/components/dashboard/NotificationsWidget.vue';
import RecentSalesWidget from '@/components/dashboard/RecentSalesWidget.vue';
import RevenueStreamWidget from '@/components/dashboard/RevenueStreamWidget.vue';
import StatsWidget from '@/components/dashboard/StatsWidget.vue';
import DashboardAnalyticsWidget from '@/components/analytics/DashboardAnalyticsWidget.vue';
import PlatformDashboard from '@/views/admin/PlatformDashboard.vue';

const auth = useAuthStore();
const isPlatformScope = computed(() => auth.scope === 'platform');
</script>

<template>
    <PlatformDashboard v-if="isPlatformScope" />

    <template v-else>
        <div class="">
            <DashboardAnalyticsWidget />
            <!-- rest of your existing dashboard -->
        </div>

        <div class="grid grid-cols-12 gap-8">
            <StatsWidget />

            <div class="col-span-12 xl:col-span-6">
                <RecentSalesWidget />
                <BestSellingWidget />
            </div>
            <div class="col-span-12 xl:col-span-6">
                <RevenueStreamWidget />
                <NotificationsWidget />
            </div>
        </div>
    </template>
</template>
