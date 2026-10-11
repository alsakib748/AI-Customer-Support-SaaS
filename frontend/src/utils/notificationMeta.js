// src/utils/notificationMeta.js

export const notificationCategories = [
    { value: 'system_health', label: 'System Health', icon: 'pi pi-heart-pulse' },
    { value: 'billing', label: 'Billing', icon: 'pi pi-credit-card' },
    { value: 'tenant', label: 'Tenants', icon: 'pi pi-building' },
    { value: 'security', label: 'Security', icon: 'pi pi-shield' },
    { value: 'ai_integrations', label: 'AI & Integrations', icon: 'pi pi-sparkles' }
];

export const notificationSeverities = [
    { value: 'info', label: 'Info' },
    { value: 'warning', label: 'Warning' },
    { value: 'error', label: 'Error' },
    { value: 'critical', label: 'Critical' }
];

export const notificationStatuses = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'read', label: 'Read' }
];

const severityStyles = {
    info: { badge: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300', dot: 'bg-sky-500' },
    warning: { badge: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300', dot: 'bg-amber-500' },
    error: { badge: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300', dot: 'bg-orange-500' },
    critical: { badge: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300', dot: 'bg-red-500' }
};

export const severityStyle = (severity) => severityStyles[severity] || severityStyles.info;

export const categoryLabel = (value) => notificationCategories.find((c) => c.value === value)?.label || 'System Health';

export const categoryIcon = (value) => notificationCategories.find((c) => c.value === value)?.icon || 'pi pi-bell';

/**
 * Compact "time ago" formatter. Falls back gracefully for invalid input.
 */
export const timeAgo = (iso) => {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '';

    const seconds = Math.round((Date.now() - then) / 1000);

    if (seconds < 45) return 'just now';

    const units = [
        { limit: 3600, div: 60, suffix: 'm' },
        { limit: 86400, div: 3600, suffix: 'h' },
        { limit: 604800, div: 86400, suffix: 'd' },
        { limit: 2629800, div: 604800, suffix: 'w' },
        { limit: 31557600, div: 2629800, suffix: 'mo' }
    ];

    for (const unit of units) {
        if (seconds < unit.limit) {
            return `${Math.floor(seconds / unit.div)}${unit.suffix} ago`;
        }
    }

    return `${Math.floor(seconds / 31557600)}y ago`;
};
