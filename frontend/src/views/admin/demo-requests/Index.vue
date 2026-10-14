<script setup>
import { reactive, ref, onMounted } from 'vue';
import demoRequestService from '@/services/demoRequestService';
import { toast } from 'vue3-toastify';

const loading = ref(false);
const saving = ref(false);
const demoRequests = ref([]);
const meta = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 });

const filters = reactive({ per_page: 20 });

const dialogVisible = ref(false);
const editForm = reactive({
    id: null,
    full_name: '',
    email: '',
    company: '',
    team_size: '',
    message: '',
    status: 'pending',
    scheduled_at: '',
    meeting_link: '',
    notes: ''
});

const statusOptions = [
    { label: 'Pending', value: 'pending' },
    { label: 'Contacted', value: 'contacted' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' }
];

const statusSeverity = {
    pending: 'warn',
    contacted: 'info',
    scheduled: 'info',
    completed: 'success',
    cancelled: 'danger'
};

const load = async (page = 1) => {
    loading.value = true;
    try {
        const { data } = await demoRequestService.getDemoRequests({ page, per_page: filters.per_page });
        demoRequests.value = data.data || [];
        Object.assign(meta, data.meta || {});
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Failed to load demo requests.');
        demoRequests.value = [];
    } finally {
        loading.value = false;
    }
};

const onPage = (event) => {
    filters.per_page = event.rows;
    load(event.page + 1);
};

const openReview = (row) => {
    Object.assign(editForm, {
        id: row.id,
        full_name: row.full_name || '',
        email: row.email || '',
        company: row.company || '',
        team_size: row.team_size || '',
        message: row.message || '',
        status: row.status || 'pending',
        scheduled_at: row.scheduled_at ? row.scheduled_at.slice(0, 16) : '',
        meeting_link: row.meeting_link || '',
        notes: row.notes || ''
    });
    dialogVisible.value = true;
};

const save = async () => {
    saving.value = true;
    try {
        const payload = {
            status: editForm.status,
            scheduled_at: editForm.scheduled_at ? editForm.scheduled_at.replace('T', ' ') : null,
            meeting_link: editForm.meeting_link || null,
            notes: editForm.notes || null
        };
        await demoRequestService.updateDemoRequest(editForm.id, payload);
        toast.success('Demo request updated.');
        dialogVisible.value = false;
        load(meta.current_page);
    } catch (error) {
        if (error?.response?.status !== 422) {
            toast.error(error?.response?.data?.message || 'Failed to update demo request.');
        }
    } finally {
        saving.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(() => load(1));
</script>

<template>
    <div class="p-4 md:p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Demo Requests</h1>
            <p class="text-surface-600 dark:text-surface-400">Leads captured from the marketing "Book a demo" form.</p>
        </div>

        <DataTable :value="demoRequests" :loading="loading" paginator :rows="filters.per_page" :totalRecords="meta.total" :lazy="true" class="w-full" @page="onPage">
            <template #empty>
                <div class="py-12 text-center text-surface-400">No demo requests yet.</div>
            </template>

            <Column header="Contact" style="min-width: 220px">
                <template #body="{ data }">
                    <div class="font-medium text-surface-900 dark:text-surface-0">{{ data.full_name }}</div>
                    <a :href="`mailto:${data.email}`" class="text-sm text-primary">{{ data.email }}</a>
                </template>
            </Column>

            <Column header="Company" style="min-width: 160px">
                <template #body="{ data }">
                    <div>{{ data.company || '—' }}</div>
                    <div v-if="data.team_size" class="text-xs text-surface-500">{{ data.team_size }} people</div>
                </template>
            </Column>

            <Column header="Status" style="min-width: 120px">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity[data.status] || 'secondary'" />
                </template>
            </Column>

            <Column header="Scheduled" style="min-width: 170px">
                <template #body="{ data }">{{ formatDate(data.scheduled_at) }}</template>
            </Column>

            <Column header="Submitted" style="min-width: 170px">
                <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
            </Column>

            <Column header="Actions" style="min-width: 120px">
                <template #body="{ data }">
                    <Button label="Review" size="small" outlined @click="openReview(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" modal header="Review demo request" :style="{ width: '34rem' }" :closable="!saving">
            <div class="flex flex-col gap-4 pt-2">
                <div>
                    <p class="text-sm text-surface-500">Contact</p>
                    <p class="font-medium text-surface-900 dark:text-surface-0">{{ editForm.full_name }}</p>
                    <p class="text-sm text-surface-600 dark:text-surface-400">{{ editForm.email }}</p>
                </div>

                <div v-if="editForm.message">
                    <p class="text-sm text-surface-500">Message</p>
                    <p class="whitespace-pre-line text-surface-700 dark:text-surface-200">{{ editForm.message }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Status</label>
                    <Select v-model="editForm.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Scheduled at</label>
                    <InputText v-model="editForm.scheduled_at" type="datetime-local" class="w-full" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Meeting link</label>
                    <InputText v-model="editForm.meeting_link" type="url" placeholder="https://meet.example.com/..." class="w-full" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Internal notes</label>
                    <Textarea v-model="editForm.notes" rows="3" autoResize class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" text :disabled="saving" @click="dialogVisible = false" />
                <Button label="Save" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>
