<!-- src/components/customers/CustomerDetails.vue -->
<script setup>
defineProps({
    customer: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['edit', 'close']);

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <div class="p-6 space-y-4">
        <div class="flex items-start gap-4">
            <Avatar
                :label="customer.initials || '?'"
                :image="customer.avatar_url"
                shape="circle"
                size="xlarge"
                :class="{
                    'bg-success': customer.status === 'active',
                    'bg-warning': customer.status === 'inactive',
                    'bg-danger': customer.status === 'blocked'
                }"
            />
            <div class="flex-1">
                <div class="text-xl font-bold">{{ customer.full_name || customer.display_name }}</div>
                <div class="text-surface-500">{{ customer.email || 'No email' }}</div>
                <div class="flex items-center gap-2 mt-1">
                    <Tag :value="customer.status_label" :severity="customer.status_color" />
                    <span v-if="customer.company_name" class="text-sm text-surface-500">
                        {{ customer.company_name }}
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <Button v-permission="'customers.update'" icon="pi pi-pencil" severity="warning" v-tooltip="'Edit customer'" @click="emit('edit')" />
                <Button icon="pi pi-times" severity="secondary" v-tooltip="'Close'" @click="emit('close')" />
            </div>
        </div>

        <Divider />

        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-3 bg-primary-50 dark:bg-primary-950 rounded-lg">
                <div class="text-2xl font-bold text-primary">{{ customer.total_conversations }}</div>
                <div class="text-sm text-surface-600 dark:text-surface-400">Conversations</div>
            </div>
            <div class="text-center p-3 bg-info-50 dark:bg-info-950 rounded-lg">
                <div class="text-2xl font-bold text-info">{{ customer.total_tickets }}</div>
                <div class="text-sm text-surface-600 dark:text-surface-400">Tickets</div>
            </div>
            <div class="text-center p-3 bg-success-50 dark:bg-success-950 rounded-lg">
                <div class="text-2xl font-bold text-success">
                    {{ customer.satisfaction_score ?? '—' }}
                </div>
                <div class="text-sm text-surface-600 dark:text-surface-400">Satisfaction</div>
            </div>
        </div>

        <div v-if="customer.tags?.length">
            <label class="text-sm text-surface-500">Tags</label>
            <div class="flex flex-wrap gap-2 mt-1">
                <Tag v-for="tag in customer.tags" :key="tag" :value="tag" severity="info" />
            </div>
        </div>

        <div v-if="customer.notes">
            <label class="text-sm text-surface-500">Notes</label>
            <div class="p-3 bg-surface-100 dark:bg-surface-800 rounded-lg mt-1 whitespace-pre-wrap">
                {{ customer.notes }}
            </div>
        </div>

        <div class="text-sm text-surface-500">
            <div>Created: {{ formatDate(customer.created_at) }}</div>
            <div v-if="customer.last_contacted_at">Last Contacted: {{ formatDate(customer.last_contacted_at) }}</div>
        </div>
    </div>
</template>
