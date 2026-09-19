import { ref } from 'vue';

/**
 * Global plan-limit dialog state.
 * Any component can call `showPlanLimit(data)` when it gets a 403 with code PLAN_LIMIT_REACHED.
 */
const visible = ref(false);
const payload = ref({ feature: null, limit: null, current: null });

export const usePlanLimit = () => ({
    visible,
    payload,
    showPlanLimit(errorOrData) {
        const data = errorOrData?.response?.data?.data ?? errorOrData?.data ?? errorOrData ?? {};

        payload.value = {
            feature: data.feature ?? null,
            limit: data.limit ?? null,
            current: data.current ?? null
        };
        visible.value = true;
    },
    hidePlanLimit() {
        visible.value = false;
    }
});

/**
 * Utility: check if a caught axios error is a plan limit error.
 */
export const isPlanLimitError = (error) => {
    return error?.response?.status === 403 && error?.response?.data?.code === 'PLAN_LIMIT_REACHED';
};
