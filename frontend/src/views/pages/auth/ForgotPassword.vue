<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import TurnstileWidget from '@/components/common/TurnstileWidget.vue';
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const authStore = useAuthStore();

const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY || '';
const turnstileToken = ref('');
const turnstileRef = ref(null);

const form = reactive({ email: '' });
const sent = ref(false);
const message = ref('');
const errorMessage = ref('');

const handleSubmit = async () => {
    errorMessage.value = '';

    if (!form.email || form.email.trim() === '') {
        toast.error('Please enter your email address.');
        return;
    }

    if (turnstileSiteKey && !turnstileToken.value) {
        toast.error('Please complete the captcha challenge before continuing.');
        return;
    }

    try {
        const result = await authStore.forgotPassword({ email: form.email, turnstile_token: turnstileToken.value });
        message.value = result?.message || 'A password reset link has been sent to your email address.';
        sent.value = true;
        toast.success('Check your inbox for the reset link.');
    } catch (err) {
        // Turnstile tokens are single-use and short-lived; clear it so the user
        // can solve a fresh challenge on retry.
        turnstileRef.value?.reset();

        errorMessage.value = err.response?.data?.errors?.email?.[0] || err.response?.data?.message || 'Could not send the reset link. Please try again.';
        // The error toast is fired by the axios response interceptor in services/api.js
    }
};
</script>

<template>
    <FloatingConfigurator />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <i class="pi pi-envelope text-primary text-5xl mb-6 inline-block"></i>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Forgot your password?</div>
                        <span class="text-muted-color font-medium"> Enter your email and we'll send you a link to reset it. </span>
                    </div>

                    <div v-if="sent" class="flex flex-col items-center justify-center gap-6 w-full max-w-sm mx-auto">
                        <p class="text-center text-muted-color font-medium">{{ message }}</p>
                        <router-link to="/login" class="w-full flex justify-center py-2 px-4 rounded-md text-white bg-teal-600 hover:bg-teal-700 text-sm font-medium"> Back to sign in </router-link>
                    </div>

                    <form v-else @submit.prevent="handleSubmit" method="post" class="w-full max-w-sm mx-auto">
                        <div>
                            <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Email</label>
                            <InputText id="email" type="email" placeholder="Email address" class="w-full mb-8" v-model="form.email" />
                        </div>

                        <TurnstileWidget v-if="turnstileSiteKey" ref="turnstileRef" v-model="turnstileToken" class="mb-4 flex justify-center" />

                        <p v-if="errorMessage" class="text-red-500 text-sm text-center mb-2">{{ errorMessage }}</p>

                        <div class="pt-2 pb-3 text-center">
                            <router-link to="/login" class="cursor-pointer text-green-600">Remembered it? <span class="text-green-400 font-bold">Sign In</span></router-link>
                        </div>

                        <button
                            type="submit"
                            :disabled="authStore.loading"
                            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50"
                        >
                            {{ authStore.loading ? 'Sending…' : 'Send reset link' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
