<script setup>
import TurnstileWidget from '@/components/common/TurnstileWidget.vue';
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const authStore = useAuthStore();

const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY || '';
const turnstileToken = ref('');
const turnstileRef = ref(null);

const form = reactive({
    email: '',
    password: '',
    remember: false
});

const notVerified = ref(false);
const resending = ref(false);

const handleLogin = async () => {
    notVerified.value = false;

    if (turnstileSiteKey && !turnstileToken.value) {
        toast.error('Please complete the captcha challenge before signing in.');
        return;
    }

    try {
        await authStore.login({ ...form, turnstile_token: turnstileToken.value });
    } catch (err) {
        // Turnstile tokens are single-use and short-lived; clear it so the user
        // can solve a fresh challenge on retry.
        turnstileRef.value?.reset();

        // Error toast is fired by the axios response interceptor in services/api.js
        if (err.response?.status === 403 && err.response?.data?.error === 'email_not_verified') {
            notVerified.value = true;
        }
    }
};

const resendVerification = async () => {
    if (!form.email) {
        toast.error('Enter your email address first.');
        return;
    }

    resending.value = true;

    try {
        await authStore.resendVerification(form.email);
        toast.success('If an account exists for that email, a verification link has been sent.');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not send a verification email.');
    } finally {
        resending.value = false;
    }
};
</script>

<template>
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-[70vh] w-full px-4 py-12">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <img src="/SupportAI-brand-assets/svg/logo-horizontal.svg" alt="SupportAI" class="mb-8 w-48 shrink-0 mx-auto" />
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Welcome to AI Customer Support!</div>
                        <span class="text-muted-color font-medium">Sign in to continue</span>
                    </div>

                    <form @submit.prevent="handleLogin" method="post">
                        <div>
                            <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Email</label>
                            <InputText id="email" type="email" placeholder="Email address" class="w-full md:w-[30rem] mb-8" v-model="form.email" />

                            <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Password</label>
                            <Password id="password" type="password" v-model="form.password" placeholder="Password" :toggleMask="true" class="mb-4" fluid :feedback="false"></Password>

                            <div class="flex items-center justify-between mt-2 mb-6 gap-8">
                                <div class="flex items-center">
                                    <Checkbox v-model="form.remember" id="rememberme" binary class="mr-2"></Checkbox>
                                    <label for="rememberme">Remember me</label>
                                </div>
                                <router-link to="/forgot-password" class="font-medium no-underline ml-2 text-right cursor-pointer text-primary">Forgot password?</router-link>
                            </div>
                            <div v-if="notVerified" class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                                <p class="mb-2">Your email address is not verified yet. Please verify it before signing in.</p>
                                <button type="button" :disabled="resending || authStore.loading" @click="resendVerification" class="font-semibold text-amber-900 underline disabled:opacity-50">
                                    {{ resending ? 'Sending…' : 'Resend verification email' }}
                                </button>
                            </div>
                            <TurnstileWidget v-if="turnstileSiteKey" ref="turnstileRef" v-model="turnstileToken" class="mb-4 flex justify-center" />
                            <div class="pt-2 pb-3 text-center">
                                <router-link to="/register" class="cursor-pointer text-green-600">Don't have an account? <span class="text-green-400 font-bold">Register</span></router-link>
                            </div>
                            <!-- <div v-if="authStore.errors" class="text-red-600 text-sm text-center pb-2">
                                <p v-if="authStore.errors.email" class="text-lg">{{ authStore.errors.email[0]
                                    }}</p>
                                <p v-if="authStore.errors.password" class="text-lg">{{ authStore.errors.password[0]
                                }}</p>
                                <p v-if="authStore.errors.account" class="text-lg">{{ authStore.errors.account[0]
                                }}</p>
                            </div> -->
                            <div>
                                <button
                                    type="submit"
                                    :disabled="authStore.loading"
                                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50"
                                >
                                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                        <svg class="h-5 w-5 text-teal-500 group-hover:text-teal-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    {{ authStore.loading ? 'Signing in...' : 'Sign in' }}
                                </button>
                            </div>
                            <!-- <Button label="Sign In" class="w-full" as="router-link" to="/"></Button> -->
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pi-eye {
    transform: scale(1.6);
    margin-right: 1rem;
}

.pi-eye-slash {
    transform: scale(1.6);
    margin-right: 1rem;
}
</style>
