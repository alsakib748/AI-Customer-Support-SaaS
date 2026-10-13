<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const route = useRoute();
const authStore = useAuthStore();

const status = ref('idle'); // 'idle' | 'verifying' | 'verified' | 'error'
const message = ref('');
const email = ref(route.query.email || '');
const resending = ref(false);

const verify = async () => {
    status.value = 'verifying';
    message.value = '';

    try {
        const result = await authStore.verifyEmail(route.params.id, route.params.hash);
        status.value = 'verified';
        message.value = result?.message || 'Your email address has been verified.';
        toast.success('Email verified successfully.');
    } catch (err) {
        status.value = 'error';
        message.value = err.response?.data?.message || 'This verification link is invalid or has expired.';
    }
};

const resend = async () => {
    if (!email.value) {
        toast.error('Please enter the email address you registered with.');
        return;
    }

    resending.value = true;

    try {
        await authStore.resendVerification(email.value);
        toast.success('If an account exists for that email, a new verification link has been sent.');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not send a verification email.');
    } finally {
        resending.value = false;
    }
};

onMounted(() => {
    if (route.params.id && route.params.hash) {
        verify();
    }
});
</script>

<template>
    <FloatingConfigurator />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <i
                            :class="{
                                'pi pi-envelope': status === 'idle' || status === 'verified',
                                'pi pi-spin pi-spinner': status === 'verifying',
                                'pi pi-times-circle': status === 'error'
                            }"
                            class="text-primary text-5xl mb-6 inline-block"
                        ></i>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">
                            <span v-if="status === 'verifying'">Verifying your email…</span>
                            <span v-else-if="status === 'verified'">Email verified</span>
                            <span v-else-if="status === 'error'">Verification failed</span>
                            <span v-else>Check your email</span>
                        </div>
                        <span class="text-muted-color font-medium">
                            <template v-if="status === 'verified'">
                                {{ message }}
                            </template>
                            <template v-else-if="status === 'error'">
                                {{ message }}
                            </template>
                            <template v-else>
                                We sent a verification link to
                                <strong>{{ email || 'your email address' }}</strong
                                >. Click the link to activate your account.
                            </template>
                        </span>
                    </div>

                    <div class="flex flex-col items-center justify-center gap-4">
                        <div v-if="status === 'verified'" class="w-full max-w-sm">
                            <router-link to="/login" class="w-full flex justify-center py-2 px-4 rounded-md text-white bg-teal-600 hover:bg-teal-700 text-sm font-medium"> Continue to sign in </router-link>
                        </div>

                        <template v-else>
                            <div class="w-full max-w-sm">
                                <label for="verify_email" class="block text-left text-surface-900 dark:text-surface-0 font-medium mb-2"> Didn't receive it? Resend the link </label>
                                <InputText id="verify_email" type="email" v-model="email" placeholder="Email address" class="w-full mb-3" fluid />
                                <button
                                    type="button"
                                    :disabled="resending || authStore.loading"
                                    @click="resend"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 disabled:opacity-50"
                                >
                                    {{ resending ? 'Sending…' : 'Resend verification email' }}
                                </button>
                            </div>
                            <router-link to="/login" class="cursor-pointer text-green-600 text-sm"> Back to <span class="text-green-400 font-bold">Sign In</span> </router-link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
