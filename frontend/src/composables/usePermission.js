import { useAuthStore } from '@/stores/auth';

export function usePermission() {
    const auth = useAuthStore();

    return {
        can: (permission) => auth.hasPermission(permission),
        canAny: (permissions) => auth.hasAnyPermission(permissions),
        canAll: (permissions) => auth.hasAllPermissions(permissions),
        hasRole: (role) => auth.hasRole(role)
    };
}
