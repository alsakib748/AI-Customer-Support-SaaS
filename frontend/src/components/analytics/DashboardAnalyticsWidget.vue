<!-- src/components/analytics/DashboardAnalyticsWidget.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import analyticsService from '@/services/analyticsService';
import { useAuthStore } from '@/stores/auth';

const data = ref(null);
const authStore = useAuthStore();
const isSuperAdmin = computed(() => authStore.isSuperAdmin);

const kpis = computed(() => {
    const summary = data.value?.summary || {};
    const val = (k) => summary[k]?.current ?? summary[k]?.value ?? 0;
    const chg = (k) => summary[k]?.change_percentage ?? null;
    const pos = (k) => summary[k]?.is_positive ?? true;

    return [
        {
            title: 'Conversations',
            value: val('total_conversations').toLocaleString?.() ?? val('total_conversations'),
            change: chg('total_conversations'),
            positive: pos('total_conversations'),
            icon: 'pi pi-comments'
        },
        {
            title: 'Resolved',
            value: val('resolved_conversations').toLocaleString?.() ?? val('resolved_conversations'),
            change: chg('resolved_conversations'),
            positive: pos('resolved_conversations'),
            icon: 'pi pi-check-circle'
        },
        {
            title: 'New Customers',
            value: val('new_customers').toLocaleString?.() ?? val('new_customers'),
            change: chg('new_customers'),
            positive: pos('new_customers'),
            icon: 'pi pi-users'
        },
        {
            title: 'Open Tickets',
            value: val('open_tickets'),
            change: null,
            positive: true,
            icon: 'pi pi-ticket'
        }
    ];
});

onMounted(async () => {
    if (isSuperAdmin.value) return;

    try {
        const response = await analyticsService.getOverview({ period: '30d' });
        if (response.data.success) {
            data.value = response.data.data;
        }
    } catch (error) {
        console.error('Dashboard widget failed:', error);
    }
});
</script>

<template>
    <div v-if="!isSuperAdmin" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- <div class="grid grid-cols-1"> -->
        <Card v-for="kpi in kpis" :key="kpi.title" class="relative">
            <template #content>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-surface-500 mb-1">{{ kpi.title }}</div>
                        <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                            {{ kpi.value }}
                        </div>
                        <div v-if="kpi.change !== null" class="text-xs mt-1" :class="kpi.positive ? 'text-success' : 'text-danger'">
                            <i :class="kpi.change >= 0 ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-right'"></i>
                            {{ Math.abs(kpi.change).toFixed(1) }}%
                            <span class="text-surface-400 ml-1">vs prev</span>
                        </div>
                    </div>
                    <i :class="kpi.icon" class="text-4xl text-primary" style="font-size: 1.5rem"></i>
                </div>
            </template>
        </Card>
    </div>
</template>
