<script setup>
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import SettingsGroupForm from '@/components/admin/settings/SettingsGroupForm.vue';
import { useSystemSettingsStore } from '@/stores/systemSettings';

const store = useSystemSettingsStore();
const recipient = ref('');
const sending = ref(false);

const send = async () => {
    sending.value = true;
    try {
        const res = await store.testEmail(recipient.value || null);
        toast.success(res.message || 'Test email sent.');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Test email failed.');
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <SettingsGroupForm group="email" />

    <div class="mt-8 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
        <div class="flex items-center gap-2 mb-1">
            <i class="pi pi-send text-primary"></i>
            <span class="font-semibold text-surface-900 dark:text-surface-0">Send test email</span>
        </div>
        <p class="text-sm text-surface-500 dark:text-surface-400 mb-4">
            Verify real delivery using the current sender configuration. Leave blank to use the default test recipient.
        </p>
        <div class="flex flex-col sm:flex-row gap-3">
            <InputText v-model="recipient" type="email" placeholder="recipient@example.com" class="flex-1" />
            <Button type="button" label="Send test email" icon="pi pi-envelope" :loading="sending" @click="send" />
        </div>
    </div>
</template>
