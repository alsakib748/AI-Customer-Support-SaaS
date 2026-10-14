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

const form = reactive({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    phone: '',
    company_name: '',
    password: '',
    password_confirmation: ''
});

const errors = reactive({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    company_name: '',
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

    if (!form.first_name || form.first_name.trim() == '') {
        errors.first_name = 'First Name field is required';
        isValid = false;
    } else if (form.first_name.length < 2) {
        errors.first_name = 'First Name at least 2 character';
        isValid = false;
    }

    if (!form.last_name || form.last_name.trim() == '') {
        errors.last_name = 'Last Name field is required';
        isValid = false;
    } else if (form.last_name.length < 3) {
        errors.last_name = 'Last Name at least 3 character';
        isValid = false;
    }

    if (!form.username || form.username.trim() == '') {
        errors.username = 'Username field is required';
        isValid = false;
    } else if (form.username.length < 3) {
        errors.username = 'Username is at least 3 character';
        isValid = false;
    } else if (!/^[a-zA-Z0-9_]+$/.test(form.username)) {
        errors.username = 'Username can only contain letters, numbers, and underscores';
        isValid = false;
    }

    if (!form.email || form.email.trim() == '') {
        errors.email = 'Email field is required';
        isValid = false;
    }

    if (!form.password || form.password.trim() == '') {
        errors.password = 'Password field is required';
        isValid = false;
    } else if (form.password.length < 8) {
        errors.password = 'Password must be at least 8 characters';
        isValid = false;
    }

    if (!form.password_confirmation || form.password_confirmation.trim() == '') {
        errors.password_confirmation = 'Confirm password field is required';
        isValid = false;
    } else if (form.password != form.password_confirmation) {
        errors.password_confirmation = 'Passwords do not match';
        isValid = false;
    }

    return isValid;
};

const onFormSubmit = async () => {
    if (!validateForm()) {
        const firstError = Object.values(errors).find((error) => error !== '');
        if (firstError) {
            toast.error(firstError);
        }
        return;
    }

    if (turnstileSiteKey && !turnstileToken.value) {
        toast.error('Please complete the captcha challenge before continuing.');
        return;
    }

    try {
        await authStore.register({ ...form, turnstile_token: turnstileToken.value });
    } catch (err) {
        // Turnstile tokens are single-use and short-lived; clear it so the user
        // can solve a fresh challenge on retry.
        turnstileRef.value?.reset();

        if (err.response?.status === 422 && err.response.data?.errors) {
            const serverErrors = err.response.data.errors;
            Object.keys(serverErrors).forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(errors, key)) {
                    errors[key] = serverErrors[key][0];
                }
            });
        }
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
                        <img src="/SupportAI-brand-assets/svg/logo-horizontal.svg" alt="SupportAI" class="mb-8 w-48 shrink-0 mx-auto" />
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Welcome to AI Customer Support!</div>
                        <span class="text-muted-color font-medium">Register to continue</span>
                    </div>

                    <form @submit.prevent="onFormSubmit" method="post">
                        <div class="grid sm:grid-cols-1 lg:grid-cols-2 sm:gap-x-2 lg:gap-x-6">
                            <div>
                                <label for="first_name" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">First Name <span class="text-red-500">*</span></label>
                                <InputText id="first_name" type="text" placeholder="First Name" v-model="form.first_name" @input="clearError('first_name')" class="w-full md:w-[30rem] mb-2" :class="{ 'p-invalid': errors.first_name }" />
                                <p v-if="errors.first_name" class="text-red-500 text-md mb-4">
                                    {{ errors.first_name }}
                                </p>
                            </div>

                            <div>
                                <label for="last_name" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Last Name <span class="text-red-500">*</span></label>
                                <InputText id="last_name" type="text" placeholder="Last Name" @input="clearError('last_name')" class="w-full md:w-[30rem] mb-2" v-model="form.last_name" :class="{ 'p-invalid': errors.last_name }" />
                                <p v-if="errors.last_name" class="text-red-500 text-md mb-4">
                                    {{ errors.last_name }}
                                </p>
                            </div>

                            <div>
                                <label for="username" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Username <span class="text-red-500">*</span></label>
                                <InputText id="username" type="text" placeholder="Username" @input="clearError('username')" class="w-full md:w-[30rem] mb-2" v-model="form.username" :class="{ 'p-invalid': errors.username }" />
                                <p v-if="errors.username" class="text-red-500 text-md mb-4">
                                    {{ errors.username }}
                                </p>
                            </div>

                            <div>
                                <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Email <span class="text-red-500">*</span></label>
                                <InputText id="email" type="email" placeholder="Email address" @input="clearError('email')" class="w-full md:w-[30rem] mb-2" v-model="form.email" :class="{ 'p-invalid': errors.email }" />
                                <p v-if="errors.email" class="text-red-500 text-md mb-4">
                                    {{ errors.email }}
                                </p>
                            </div>

                            <div>
                                <label for="phone" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Phone</label>
                                <InputText id="phone" type="number" placeholder="Phone Number" class="w-full md:w-[30rem] mb-8" v-model="form.phone" />
                            </div>

                            <div>
                                <label for="company_name" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Company / Workspace Name</label>
                                <InputText
                                    id="company_name"
                                    type="text"
                                    placeholder="Company / Workspace Name"
                                    @input="clearError('company_name')"
                                    class="w-full md:w-[30rem] mb-2"
                                    v-model="form.company_name"
                                    :class="{ 'p-invalid': errors.company_name }"
                                />
                                <p v-if="errors.company_name" class="text-red-500 text-md mb-4">
                                    {{ errors.company_name }}
                                </p>
                            </div>

                            <div class="">
                                <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Password (min 8 characters) <span class="text-red-500">*</span></label>
                                <Password
                                    id="password"
                                    type="password"
                                    v-model="form.password"
                                    placeholder="Password"
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

                            <div class="">
                                <label for="confirm_password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Confirm Password <span class="text-red-500">*</span> </label>
                                <Password
                                    id="confirm_password"
                                    type="password"
                                    v-model="form.password_confirmation"
                                    @input="clearError('password_confirmation')"
                                    placeholder="Confirm Password"
                                    :toggleMask="true"
                                    class="mb-2"
                                    fluid
                                    :feedback="false"
                                    :class="{ 'p-invalid': errors.password_confirmation }"
                                >
                                </Password>
                                <p v-if="errors.password_confirmation" class="text-red-500 text-md mb-4">
                                    {{ errors.password_confirmation }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center">
                            <TurnstileWidget v-if="turnstileSiteKey" ref="turnstileRef" v-model="turnstileToken" class="mb-4" />
                            <div class="pt-2 pb-3 text-center">
                                <router-link to="/login" class="cursor-pointer text-green-600">Already have an account? <span class="text-green-400 font-bold">Sign In</span></router-link>
                            </div>
                            <div>
                                <!-- :disabled="authStore.loading || !form.first_name || !form.last_name || !form.email || !form.password" -->
                                <button
                                    type="submit"
                                    class="group relative w-120 flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                        <svg v-if="!authStore.loading" class="h-5 w-5 text-teal-500 group-hover:text-teal-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-else class="h-5 w-5 text-teal-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </span>
                                    {{ authStore.loading ? 'Creating Account...' : 'Create Account' }}
                                </button>
                            </div>
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
