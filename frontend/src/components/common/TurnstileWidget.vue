<script setup>
/**
 * Cloudflare Turnstile widget.
 *
 * Renders the managed challenge using explicit rendering and emits the token
 * through v-model. When no site key is configured the widget renders nothing
 * and stays silent, so the app works in development without bot protection.
 *
 * Usage:
 *   <TurnstileWidget v-model="turnstileToken" />
 *   ... reset after a failed submit: widgetRef.value?.reset()
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sitekey: {
        type: String,
        default: () => import.meta.env.VITE_TURNSTILE_SITE_KEY || ''
    },
    theme: {
        type: String,
        default: 'auto'
    },
    action: {
        type: String,
        default: 'turnstile'
    }
});

const emit = defineEmits(['update:modelValue', 'expired', 'error']);

const container = ref(null);
const widgetId = ref(null);

let scriptPromise = null;

const loadScript = () => {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-turnstile]');

        if (existing) {
            existing.addEventListener('load', () => resolve(window.turnstile));
            existing.addEventListener('error', () => reject(new Error('Failed to load Turnstile')));
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.dataset.turnstile = 'true';
        script.onload = () => resolve(window.turnstile);
        script.onerror = () => reject(new Error('Failed to load Turnstile'));
        document.head.appendChild(script);
    });

    return scriptPromise;
};

const setToken = (value) => {
    emit('update:modelValue', value);
};

const renderWidget = async () => {
    if (!props.sitekey || !container.value) {
        return;
    }

    try {
        const turnstile = await loadScript();

        if (!turnstile || !container.value) {
            return;
        }

        widgetId.value = turnstile.render(container.value, {
            sitekey: props.sitekey,
            theme: props.theme,
            action: props.action,
            callback: (token) => setToken(token),
            'expired-callback': () => {
                setToken('');
                emit('expired');
            },
            'error-callback': () => {
                setToken('');
                emit('error');
            }
        });
    } catch (error) {
        emit('error', error);
    }
};

const reset = () => {
    setToken('');

    if (window.turnstile && widgetId.value !== null) {
        window.turnstile.reset(widgetId.value);
    }
};

onMounted(renderWidget);

onBeforeUnmount(() => {
    if (window.turnstile && widgetId.value !== null) {
        window.turnstile.remove(widgetId.value);
        widgetId.value = null;
    }
});

defineExpose({ reset });
</script>

<template>
    <div v-if="sitekey" ref="container" class="cf-turnstile-container" />
</template>
