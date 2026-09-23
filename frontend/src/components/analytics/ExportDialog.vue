<!-- src/components/analytics/ExportDialog.vue -->
<script setup>
import { reactive, ref } from 'vue';
import analyticsService from '@/services/analyticsService';
import { toast } from 'vue3-toastify';

const props = defineProps({
    visible: { type: Boolean, default: false }
});
const emit = defineEmits(['update:visible', 'queued']);

const loading = ref(false);

const form = reactive({
    type: 'conversations',
    period: '30d',
    from: null,
    to: null
});

const typeOptions = [
    { label: 'Conversations', value: 'conversations' },
    { label: 'Customers', value: 'customers' },
    { label: 'Agents', value: 'agents' },
    { label: 'Tickets', value: 'tickets' },
    { label: 'AI Usage', value: 'ai' },
    { label: 'Chat Widget', value: 'widget' }
];

const periodOptions = [
    { label: 'Today', value: 'today' },
    { label: 'Last 7 Days', value: '7d' },
    { label: 'Last 30 Days', value: '30d' },
    { label: 'Last 90 Days', value: '90d' },
    { label: 'This Month', value: 'this_month' },
    { label: 'Last Month', value: 'last_month' },
    { label: 'Custom Range', value: 'custom' }
];

const submit = async () => {
    loading.value = true;
    try {
        const response = await analyticsService.requestExport({ ...form });
        if (response.data.success) {
            toast.success(response.data.message || 'Export queued successfully.');
            emit('queued', response.data.data);
            emit('update:visible', false);
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to queue export.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" @update:visible="$emit('update:visible', $event)" header="Export Report" :style="{ width: '480px' }" modal>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Report Type *</label>
                <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="Select report type" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Period</label>
                <Select v-model="form.period" :options="periodOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>

            <div v-if="form.period === 'custom'" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">From</label>
                    <Calendar v-model="form.from" dateFormat="yy-mm-dd" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">To</label>
                    <Calendar v-model="form.to" dateFormat="yy-mm-dd" class="w-full" />
                </div>
            </div>

            <div class="text-xs text-surface-500">
                <i class="pi pi-info-circle mr-1"></i>
                Large exports are generated in the background. You'll be notified when ready.
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" @click="$emit('update:visible', false)" />
            <Button label="Export CSV" icon="pi pi-download" severity="primary" :loading="loading" :disabled="!form.type" @click="submit" />
        </template>
    </Dialog>
</template>
