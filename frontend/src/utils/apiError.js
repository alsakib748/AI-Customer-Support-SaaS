import { toast } from 'vue3-toastify';

/**
 * Convert any Axios error into one or more vue3-toastify toasts.
 *
 * Laravel ValidationException shape (status 422):
 *   { success: false, message: "Validation failed", errors: { field: ["msg", ...] } }
 *
 * Returns void (fire-and-forget). Callers may `throw err` afterwards
 * if they want to abort further handling.
 */
export function showApiError(err, fallback = 'Something went wrong. Please try again.') {
    const status = err?.response?.status;
    const data = err?.response?.data;

    // 422 — Laravel validation errors: one toast per field message
    if (status === 422 && data?.errors && typeof data.errors === 'object') {
        const flat = Object.values(data.errors).flat();
        if (flat.length === 0) {
            toast.error(data.message || 'Validation failed');
            return;
        }
        // Cap at 5 toasts so a 20-field form doesn't bury the UI
        flat.slice(0, 5).forEach((msg) => toast.error(msg));
        if (flat.length > 5) {
            toast.warning(`...and ${flat.length - 5} more error(s).`);
        }
        return;
    }

    // 401 — handled by the interceptor (auth redirect). Don't toast here.
    if (status === 401) return;

    // 403 / 404 / 500 / network / anything else
    const message = data?.message || err?.message || fallback;

    toast.error(message);
}

