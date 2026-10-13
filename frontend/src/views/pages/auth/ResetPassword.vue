<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const email = ref(route.query.email || '');
const token = ref(route.query.token || '');
const errorMessage = ref('');

const form = reactive({
    password: '',
    password_confirmation: ''
});

const errors = reactive({
    password: '',
    password_confirmation: ''
});

const clearError = (field) => {
    if (errors[field]) {
        errors[field] = '';
    }
};

const validateForm = () => {
    let isValid = true;

    Object.keys(errors).forEach((key) => (errors[key] = ''));

    if (!form.password || form.password.trim() === '') {
        errors.password = 'Password field is required';
        isValid = false;
    } else if (form.password.length < 8) {
        errors.password = 'Password must be at least 8 characters';
        isValid = false;
    }

    if (!form.password_confirmation || form.password_confirmation.trim() === '') {
        errors.password_confirmation = 'Confirm password field is required';
        isValid = false;
    } else if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Passwords do not match';
        isValid = false;
    }

    return isValid;
};

const handleSubmit = async () => {
    errorMessage.value = '';

    if (!validateForm()) {
        const firstError = Object.values(errors).find((error) => error !== '');
        if (firstError) {
            toast.error(firstError);
        }
        return;
    }

    try {
        await authStore.resetPassword({
            email: email.value,
            token: token.value,
            password: form.password,
            password_confirmation: form.password_confirmation
        });

        toast.success('Password reset successfully. Signing you in…');
        router.push('/dashboard');
    } catch (err) {
        const serverErrors = err.response?.data?.errors;
        errorMessage.value = (serverErrors && Object.values(serverErrors)[0]?.[0]) || err.response?.data?.message || 'Password reset failed. The link may be invalid or have expired.';
        // Error toast is fired by the axios response interceptor in services/api.js
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
                        <i class="pi pi-lock text-primary text-5xl mb-6 inline-block"></i>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Reset your password</div>
                        <span v-if="email" class="text-muted-color font-medium">
                            Choose a new password for <strong>{{ email }}</strong>
                        </span>
                        <span v-else class="text-muted-color font-medium"> Choose a new password for your account. </span>
                    </div>

                    <div v-if="!token || !email" class="flex flex-col items-center justify-center gap-6 w-full max-w-sm mx-auto">
                        <p class="text-center text-red-500 font-medium">This password reset link is invalid or incomplete. Please request a new one.</p>
                        <router-link to="/forgot-password" class="w-full flex justify-center py-2 px-4 rounded-md text-white bg-teal-600 hover:bg-teal-700 text-sm font-medium"> Request a new link </router-link>
                    </div>

                    <form v-else @submit.prevent="handleSubmit" method="post" class="w-full max-w-sm mx-auto">
                        <div>
                            <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">New Password (min 8 characters)</label>
                            <Password
                                id="password"
                                type="password"
                                v-model="form.password"
                                placeholder="New password"
                                @input="clearError('password')"
                                :toggleMask="true"
                                class="mb-2"
                                fluid
                                :feedback="false"
                                :class="{ 'p-invalid': errors.password }"
                            ></Password>
                            <p v-if="errors.password" class="text-red-500 text-md mb-4">
                                {{ errors.password }}
                            </p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Confirm New Password</label>
                            <Password
                                id="confirm_password"
                                type="password"
                                v-model="form.password_confirmation"
                                placeholder="Confirm new password"
                                @input="clearError('password_confirmation')"
                                :toggleMask="true"
                                class="mb-2"
                                fluid
                                :feedback="false"
                                :class="{ 'p-invalid': errors.password_confirmation }"
                            ></Password>
                            <p v-if="errors.password_confirmation" class="text-red-500 text-md mb-4">
                                {{ errors.password_confirmation }}
                            </p>
                        </div>

                        <p v-if="errorMessage" class="text-red-500 text-sm text-center my-3">{{ errorMessage }}</p>

                        <div class="pt-2 pb-3 text-center">
                            <router-link to="/login" class="cursor-pointer text-green-600">Back to <span class="text-green-400 font-bold">Sign In</span></router-link>
                        </div>

                        <button
                            type="submit"
                            :disabled="authStore.loading"
                            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50"
                        >
                            {{ authStore.loading ? 'Resetting…' : 'Reset password' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
