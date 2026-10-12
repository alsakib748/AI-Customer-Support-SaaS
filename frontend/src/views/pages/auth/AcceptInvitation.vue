<!-- src/views/pages/auth/AcceptInvitation.vue -->
<script setup>
import { reactive, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import teamService from '@/services/teamService';

const route = useRoute();
const router = useRouter();

const token = computed(() => route.params.token);
const submitting = ref(false);
const accepted = ref(false);
const generalError = ref('');
const fieldErrors = ref({});

const form = reactive({
    email: '',
    first_name: '',
    last_name: '',
    password: ''
});

const handleAccept = async () => {
    submitting.value = true;
    generalError.value = '';
    fieldErrors.value = {};

    try {
        await teamService.acceptInvitation(token.value, { ...form });
        accepted.value = true;
    } catch (error) {
        const response = error?.response?.data;
        fieldErrors.value = response?.errors || {};
        generalError.value = response?.message || 'Unable to accept this invitation. It may be invalid or expired.';
    } finally {
        submitting.value = false;
    }
};

const goToLogin = () => router.push('/login');
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-surface-50 dark:bg-surface-950 px-4">
        <div class="w-full max-w-md bg-surface-0 dark:bg-surface-900 rounded-2xl shadow-lg p-8">
            <template v-if="accepted">
                <div class="text-center">
                    <i class="pi pi-check-circle text-5xl text-green-500 mb-4"></i>
                    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0 mb-2">Invitation accepted</h1>
                    <p class="text-surface-500 mb-6">You have joined the workspace. Sign in to get started.</p>
                    <Button label="Go to sign in" class="w-full" @click="goToLogin" />
                </div>
            </template>

            <template v-else>
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0 mb-2">Accept your invitation</h1>
                    <p class="text-surface-500">Confirm your details to join the workspace.</p>
                </div>

                <form @submit.prevent="handleAccept">
                    <div class="mb-4">
                        <label for="email" class="block text-surface-900 dark:text-surface-0 font-medium mb-2">Email</label>
                        <InputText id="email" type="email" v-model="form.email" class="w-full" placeholder="you@example.com" />
                        <small v-if="fieldErrors.email" class="block text-red-500 mt-1">{{ fieldErrors.email[0] }}</small>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="first_name" class="block text-surface-900 dark:text-surface-0 font-medium mb-2">First name</label>
                            <InputText id="first_name" v-model="form.first_name" class="w-full" />
                        </div>
                        <div>
                            <label for="last_name" class="block text-surface-900 dark:text-surface-0 font-medium mb-2">Last name</label>
                            <InputText id="last_name" v-model="form.last_name" class="w-full" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium mb-2">Password</label>
                        <Password id="password" v-model="form.password" :toggleMask="true" :feedback="false" fluid placeholder="At least 8 characters" />
                        <small v-if="fieldErrors.password" class="block text-red-500 mt-1">{{ fieldErrors.password[0] }}</small>
                    </div>

                    <p v-if="generalError" class="text-red-500 text-sm mb-4">{{ generalError }}</p>

                    <Button type="submit" label="Accept invitation" class="w-full" :loading="submitting" />
                </form>
            </template>
        </div>
    </div>
</template>
