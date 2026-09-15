<!-- src/views/analytics/ExportHistory.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import analyticsService from '@/services/analyticsService';
import ExportDialog from '@/components/analytics/ExportDialog.vue';
import { toast } from 'vue3-toastify';

const exports = ref([]);
const loading = ref(false);
const showExportDialog = ref(false);

const loadExports = async () => {
    loading.value = true;
    try {
        const response = await analyticsService.getExports();
        if (response.data.success) {
            exports.value = response.data.data || [];
        }
    } catch (error) {
        toast.error('Failed to load exports.');
    } finally {
        loading.value = false;
    }
};

const download = async (item) => {
    if (!item.download_url) return;

    try {
        const response = await analyticsService.downloadExport(item.id);
        const blobUrl = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = item.file_name || `analytics-export-${item.id}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(blobUrl);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to download export.');
    }
};

const statusColor = (status) => ({
    pending: 'warning',
    processing: 'info',
    completed: 'success',
    failed: 'danger',
}[status] || 'secondary');

const formatDate = (d) => d ? new Date(d).toLocaleString() : '—';

onMounted(loadExports);
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Export History</h1>
                <p class="text-surface-600 dark:text-surface-400">
                    Download previously generated reports
                </p>
            </div>
            <Button label="New Export" icon="pi pi-plus" severity="primary" @click="showExportDialog = true" />
        </div>

        <DataTable :value="exports" :loading="loading" paginator :rows="15" :rowsPerPageOptions="[15, 30, 50]"
            responsiveLayout="scroll">

            <Column field="type" header="Type">
                <template #body="{ data }">
                    <span class="font-medium capitalize">{{ data.type }}</span>
                </template>
            </Column>

            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusColor(data.status)" />
                </template>
            </Column>

            <Column field="row_count" header="Rows">
                <template #body="{ data }">{{ data.row_count ?? '—' }}</template>
            </Column>

            <Column field="created_at" header="Requested At">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>

            <Column field="completed_at" header="Completed At">
                <template #body="{ data }">
                    {{ data.completed_at ? formatDate(data.completed_at) : '—' }}
                </template>
            </Column>

            <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                    <Button v-if="data.is_ready" icon="pi pi-download" severity="primary" text rounded
                        @click="download(data)" tooltip="Download" />
                    <span v-else-if="data.status === 'failed'" class="text-danger text-xs">
                        {{ data.error_message || 'Failed' }}
                    </span>
                    <i v-else class="pi pi-spin pi-spinner text-primary"></i>
                </template>
            </Column>

            <template #empty>
                <div class="text-center py-12 text-surface-500">
                    <i class="pi pi-inbox text-4xl mb-2 block"></i>
                    <p>No exports yet</p>
                </div>
            </template>
        </DataTable>

        <ExportDialog v-model:visible="showExportDialog" @queued="loadExports" />

        <!-- Error dialog -->
        <Dialog v-model:visible="showErrorDialog" header="Export Error" :style="{ width: '500px' }" modal>
            <div class="text-sm whitespace-pre-wrap break-words">
                {{ errorMessage }}
            </div>
            <template #footer>
                <Button label="Close" severity="secondary" @click="showErrorDialog = false" />
            </template>
        </Dialog>

    </div>
</template>
