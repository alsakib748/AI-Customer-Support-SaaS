<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { toast } from 'vue3-toastify';
import { useSystemSettingsStore } from '@/stores/systemSettings';
import { useAuthStore } from '@/stores/auth';
import SettingsField from './SettingsField.vue';
import SecretField from './SecretField.vue';
import SettingsSaveBar from './SettingsSaveBar.vue';

const props = defineProps({
    group: { type: String, required: true }
});

const store = useSystemSettingsStore();
const auth = useAuthStore();

const meta = ref(null);
const fields = ref([]);
const form = reactive({});
const configured = reactive({});
const clearSecrets = reactive({});

const loading = ref(true);
const saving = ref(false);
const dirty = ref(false);
let hydrating = false;

const canEdit = computed(
    () => auth.isSuperAdmin || auth.hasPermission(`platform.settings.${props.group}`)
);

const isFullWidth = (field) => ['text', 'array'].includes(field.type);

const fallbackFor = (field) => {
    if (field.type === 'bool') return false;
    if (field.type === 'array') return [];
    return null;
};

const applyPayload = (payload) => {
    hydrating = true;
    meta.value = payload?.group || null;
    fields.value = payload?.group?.fields || [];

    Object.keys(form).forEach((key) => delete form[key]);
    Object.keys(configured).forEach((key) => delete configured[key]);
    Object.keys(clearSecrets).forEach((key) => delete clearSecrets[key]);

    fields.value.forEach((field) => {
        if (field.sensitive) {
            form[field.key] = '';
            configured[field.key] = Boolean(payload?.secrets?.[field.key]);
            clearSecrets[field.key] = false;
        } else {
            form[field.key] = payload?.values?.[field.key] ?? fallbackFor(field);
        }
    });

    dirty.value = false;
    hydrating = false;
};

const load = async () => {
    loading.value = true;
    try {
        const payload = await store.fetchGroup(props.group);
        applyPayload(payload);
    } catch (error) {
        // interceptor already surfaced the error
    } finally {
        loading.value = false;
    }
};

const markDirty = () => {
    if (!hydrating) dirty.value = true;
};

watch(form, markDirty, { deep: true });
watch(clearSecrets, markDirty, { deep: true });

const buildPayload = () => {
    const payload = {};

    fields.value.forEach((field) => {
        if (field.sensitive) {
            if (clearSecrets[field.key]) {
                payload[field.key] = '';
            } else if (form[field.key] !== '' && form[field.key] !== null) {
                payload[field.key] = form[field.key];
            }
            // untouched / empty secret => omit (leave as-is)
            return;
        }

        payload[field.key] = form[field.key];
    });

    return payload;
};

const save = async () => {
    saving.value = true;
    try {
        const payload = await store.updateGroup(props.group, buildPayload());
        applyPayload(payload);
        toast.success(`${meta.value?.label || 'Settings'} saved.`);
    } catch (error) {
        // interceptor already surfaced the error
    } finally {
        saving.value = false;
    }
};

const reset = () => {
    const current = store.payloadFor(props.group);
    if (current) applyPayload(current);
    else load();
};

onMounted(load);
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-16">
            <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
        </div>

        <form v-else @submit.prevent="save" class="space-y-6">
            <div v-if="meta" class="mb-2">
                <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">{{ meta.label }}</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">{{ meta.description }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="field in fields" :key="field.key" :class="{ 'md:col-span-2': isFullWidth(field) }">
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                        {{ field.label }}
                    </label>

                    <SecretField
                        v-if="field.sensitive"
                        :modelValue="form[field.key]"
                        :configured="configured[field.key]"
                        :cleared="clearSecrets[field.key]"
                        @update:modelValue="form[field.key] = $event"
                        @update:cleared="clearSecrets[field.key] = $event"
                    />
                    <SettingsField v-else :field="field" :modelValue="form[field.key]" @update:modelValue="form[field.key] = $event" />

                    <small v-if="field.help" class="block mt-1 text-surface-500 dark:text-surface-400">{{ field.help }}</small>
                </div>
            </div>

            <SettingsSaveBar :saving="saving" :dirty="dirty" :disabled="!canEdit" @save="save" @reset="reset" />
        </form>
    </div>
</template>
