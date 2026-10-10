// src/stores/systemSettings.js

import { defineStore } from 'pinia';
import { ref } from 'vue';
import systemSettingsService from '@/services/systemSettingsService';

export const useSystemSettingsStore = defineStore('systemSettings', () => {
    // =========================================================
    // STATE
    // =========================================================
    const groups = ref([]);

    // group key -> { group, values, secrets, meta }
    const payloads = ref({});

    const integrations = ref([]);
    const health = ref(null);

    const loading = ref(false);
    const saving = ref(false);
    const testing = ref(false);
    const error = ref(null);

    // =========================================================
    // HELPERS
    // =========================================================
    const setError = (err, fallback) => {
        error.value = err?.response?.data?.message || fallback;
    };

    const payloadFor = (group) => payloads.value[group] || null;

    // =========================================================
    // ACTIONS — HUB
    // =========================================================
    const fetchIndex = async () => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemSettingsService.getIndex();
            groups.value = data.data?.groups || [];
            return groups.value;
        } catch (err) {
            setError(err, 'Failed to load settings');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // ACTIONS — GROUP
    // =========================================================
    const fetchGroup = async (group) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemSettingsService.getGroup(group);
            payloads.value = { ...payloads.value, [group]: data.data };
            return data.data;
        } catch (err) {
            setError(err, 'Failed to load settings');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateGroup = async (group, values) => {
        saving.value = true;
        error.value = null;
        try {
            const { data } = await systemSettingsService.updateGroup(group, values);
            payloads.value = { ...payloads.value, [group]: data.data };
            return data.data;
        } catch (err) {
            setError(err, 'Failed to save settings');
            throw err;
        } finally {
            saving.value = false;
        }
    };

    // =========================================================
    // ACTIONS — EMAIL
    // =========================================================
    const testEmail = async (recipient = null) => {
        testing.value = true;
        try {
            const { data } = await systemSettingsService.testEmail(
                recipient ? { recipient } : {}
            );
            return data;
        } finally {
            testing.value = false;
        }
    };

    // =========================================================
    // ACTIONS — INTEGRATIONS
    // =========================================================
    const fetchIntegrations = async () => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemSettingsService.getIntegrations();
            integrations.value = data.data?.providers || [];
            return integrations.value;
        } catch (err) {
            setError(err, 'Failed to load integrations');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const testIntegration = async (provider) => {
        testing.value = true;
        try {
            const { data } = await systemSettingsService.testIntegration(provider);
            // Reflect the new status in the list.
            const idx = integrations.value.findIndex((p) => p.key === provider);
            if (idx !== -1 && data.data) {
                integrations.value[idx] = {
                    ...integrations.value[idx],
                    ...data.data
                };
            }
            return data;
        } finally {
            testing.value = false;
        }
    };

    // =========================================================
    // ACTIONS — OPERATIONS / HEALTH
    // =========================================================
    const fetchHealth = async () => {
        try {
            const { data } = await systemSettingsService.getHealth();
            health.value = data.data;
            return health.value;
        } catch (err) {
            setError(err, 'Failed to load system health');
            throw err;
        }
    };

    return {
        // State
        groups,
        payloads,
        integrations,
        health,
        loading,
        saving,
        testing,
        error,

        // Helpers
        payloadFor,

        // Actions
        fetchIndex,
        fetchGroup,
        updateGroup,
        testEmail,
        fetchIntegrations,
        testIntegration,
        fetchHealth
    };
});
