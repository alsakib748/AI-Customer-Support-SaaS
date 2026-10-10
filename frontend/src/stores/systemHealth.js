// src/stores/systemHealth.js

import { defineStore } from 'pinia';
import { ref } from 'vue';
import systemHealthService from '@/services/systemHealthService';

export const useSystemHealthStore = defineStore('systemHealth', () => {
    // =========================================================
    // STATE
    // =========================================================
    const snapshot = ref(null); // { overall, generated_at, counts, active_incidents, stale_after_seconds, checks }
    const checks = ref([]); // rows from snapshot.checks
    const checkDetail = ref(null); // { latest, history } for a single check
    const incidents = ref([]);
    const incident = ref(null);
    const metrics = ref(null);
    const events = ref([]);

    const loading = ref(false);
    const running = ref(false); // a manual check is in-flight
    const managing = ref(false);
    const error = ref(null);

    let pollTimer = null;

    // =========================================================
    // HELPERS
    // =========================================================
    const setError = (err, fallback) => {
        error.value = err?.response?.data?.message || fallback || null;
    };

    const statusTone = (status) => {
        const tones = {
            healthy: 'success',
            degraded: 'warn',
            unhealthy: 'danger',
            unknown: 'secondary',
            disabled: 'secondary'
        };
        return tones[status] || 'secondary';
    };

    // =========================================================
    // SNAPSHOT
    // =========================================================
    const fetchSnapshot = async (fresh = false) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.getSnapshot(fresh ? { fresh: 1 } : {});
            snapshot.value = data.data;
            checks.value = data.data?.checks || [];
            return snapshot.value;
        } catch (err) {
            setError(err, 'Failed to load system health');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // =========================================================
    // CHECKS
    // =========================================================
    const fetchCheck = async (key) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.getCheck(key);
            checkDetail.value = data.data;
            return checkDetail.value;
        } catch (err) {
            setError(err, 'Failed to load check');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const runCheck = async (key) => {
        running.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.runCheck(key);
            // Refresh so the dashboard reflects the latest outcome.
            return data.data;
        } catch (err) {
            setError(err, 'Failed to run check');
            throw err;
        } finally {
            running.value = false;
        }
    };

    // =========================================================
    // INCIDENTS
    // =========================================================
    const fetchIncidents = async (params = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.getIncidents(params);
            incidents.value = data.data || [];
            return data;
        } catch (err) {
            setError(err, 'Failed to load incidents');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchIncident = async (id) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.getIncident(id);
            incident.value = data.data;
            return incident.value;
        } catch (err) {
            setError(err, 'Failed to load incident');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const acknowledgeIncident = async (id) => {
        managing.value = true;
        try {
            const { data } = await systemHealthService.acknowledgeIncident(id);
            incident.value = data.data;
            return data.data;
        } finally {
            managing.value = false;
        }
    };

    const resolveIncident = async (id) => {
        managing.value = true;
        try {
            const { data } = await systemHealthService.resolveIncident(id);
            incident.value = data.data;
            return data.data;
        } finally {
            managing.value = false;
        }
    };

    // =========================================================
    // METRICS & EVENTS
    // =========================================================
    const fetchMetrics = async (params = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await systemHealthService.getMetrics(params);
            metrics.value = data.data;
            return metrics.value;
        } catch (err) {
            setError(err, 'Failed to load metrics');
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchEvents = async (params = {}) => {
        try {
            const { data } = await systemHealthService.getEvents(params);
            events.value = data.data || [];
            return events.value;
        } catch (err) {
            setError(err, 'Failed to load events');
            throw err;
        }
    };

    // =========================================================
    // POLLING
    // =========================================================
    const startPolling = (intervalMs = 30000) => {
        stopPolling();
        pollTimer = setInterval(() => {
            if (!document.hidden) {
                fetchSnapshot().catch(() => {});
            }
        }, intervalMs);
    };

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    return {
        // State
        snapshot,
        checks,
        checkDetail,
        incidents,
        incident,
        metrics,
        events,
        loading,
        running,
        managing,
        error,

        // Helpers
        statusTone,

        // Actions
        fetchSnapshot,
        fetchCheck,
        runCheck,
        fetchIncidents,
        fetchIncident,
        acknowledgeIncident,
        resolveIncident,
        fetchMetrics,
        fetchEvents,
        startPolling,
        stopPolling
    };
});
