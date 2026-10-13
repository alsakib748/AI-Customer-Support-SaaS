import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';

const stops = new WeakMap();

function allows(value, modifiers) {
    const auth = useAuthStore();

    if (value === null || value === undefined || value === '') return true;

    const perms = Array.isArray(value) ? value : [value];
    if (perms.length === 0) return true;

    if (auth.isSuperAdmin) return true;

    return modifiers && modifiers.any ? auth.hasAnyPermission(perms) : auth.hasAllPermissions(perms);
}

function apply(el, binding) {
    const ok = allows(binding.value, binding.modifiers);

    if (ok) {
        if (el.dataset.permissionHidden === '1') {
            el.style.display = el.dataset.permissionDisplay || '';
            delete el.dataset.permissionHidden;
        }
        return;
    }

    if (el.dataset.permissionHidden !== '1') {
        el.dataset.permissionDisplay = el.style.display && el.style.display !== 'none' ? el.style.display : '';
    }

    el.style.display = 'none';
    el.dataset.permissionHidden = '1';
}

export default {
    mounted(el, binding) {
        el.__permissionBinding = binding;
        apply(el, binding);

        const auth = useAuthStore();
        stops.set(
            el,
            watch(
                () => [auth.permissions, auth.role, auth.scope],
                () => apply(el, el.__permissionBinding || binding),
                { deep: true }
            )
        );
    },

    updated(el, binding) {
        el.__permissionBinding = binding;
        apply(el, binding);
    },

    unmounted(el) {
        stops.get(el)?.();
        stops.delete(el);
        delete el.__permissionBinding;
    }
};
