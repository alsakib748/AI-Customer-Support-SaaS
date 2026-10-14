<script setup>
import { reactive, ref } from 'vue';
import { Check, AlertCircle } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';

// Public demo request endpoint. Validate and rate-limit again on the server.
const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';
const ENDPOINT = `${API_BASE}/demo-requests`;

const form = reactive({ name: '', email: '', company: '', message: '', website: '' }); // website = honeypot
const errors = reactive({ name: '', email: '', message: '' });
const status = ref('idle'); // idle | submitting | success | error

function validate() {
    errors.name = form.name.trim() ? '' : 'Enter your name.';
    errors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()) ? '' : 'Enter a valid work email, like name@company.com.';
    errors.message = form.message.trim().length >= 10 ? '' : 'Tell us a little more, at least 10 characters.';
    return !errors.name && !errors.email && !errors.message;
}

async function submit() {
    if (status.value === 'submitting') return;
    if (!validate()) return;
    if (form.website) {
        // A bot filled the hidden field. Pretend success and send nothing.
        status.value = 'success';
        return;
    }
    status.value = 'submitting';
    try {
        const res = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ full_name: form.name.trim(), email: form.email.trim(), company: form.company.trim(), message: form.message.trim() })
        });
        if (!res.ok) throw new Error(`Request failed: ${res.status}`);
        status.value = 'success';
        Object.assign(form, { name: '', email: '', company: '', message: '', website: '' });
    } catch {
        status.value = 'error';
    }
}

const field = 'w-full rounded-xl border bg-card px-4 py-3 text-base text-ink placeholder:text-muted transition-colors focus:border-brand focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-400/30';
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <div v-if="status === 'success'" role="status" class="flex items-start gap-3 rounded-xl border border-line bg-ok-bg p-4 text-ok-fg">
            <Check class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p><strong>Message sent.</strong> We will reply to your email soon.</p>
        </div>
        <div v-if="status === 'error'" role="alert" class="flex items-start gap-3 rounded-xl border border-line bg-danger-bg p-4 text-danger-fg">
            <AlertCircle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p><strong>We could not send your message.</strong> Check your connection and try again.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <label for="c-name" class="text-sm font-semibold text-ink">Name</label>
                <input id="c-name" v-model="form.name" type="text" autocomplete="name" :class="[field, errors.name ? 'border-danger-fg' : 'border-line']" :aria-invalid="!!errors.name" :aria-describedby="errors.name ? 'c-name-err' : undefined" />
                <p v-if="errors.name" id="c-name-err" class="text-sm text-danger-fg">{{ errors.name }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="c-email" class="text-sm font-semibold text-ink">Work email</label>
                <input
                    id="c-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    :class="[field, errors.email ? 'border-danger-fg' : 'border-line']"
                    :aria-invalid="!!errors.email"
                    :aria-describedby="errors.email ? 'c-email-err' : undefined"
                />
                <p v-if="errors.email" id="c-email-err" class="text-sm text-danger-fg">{{ errors.email }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="c-company" class="text-sm font-semibold text-ink">Company <span class="font-normal text-muted">(optional)</span></label>
            <input id="c-company" v-model="form.company" type="text" autocomplete="organization" :class="[field, 'border-line']" />
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="c-message" class="text-sm font-semibold text-ink">What are you trying to solve?</label>
            <textarea
                id="c-message"
                v-model="form.message"
                rows="5"
                :class="[field, errors.message ? 'border-danger-fg' : 'border-line']"
                :aria-invalid="!!errors.message"
                :aria-describedby="errors.message ? 'c-message-err' : 'c-message-help'"
            ></textarea>
            <p v-if="errors.message" id="c-message-err" class="text-sm text-danger-fg">{{ errors.message }}</p>
            <p v-else id="c-message-help" class="text-sm text-muted">For example, your support volume and the channels you use today.</p>
        </div>

        <!-- Honeypot: hidden from people, visible to simple bots. -->
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="c-website">Website</label>
            <input id="c-website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
        </div>

        <BaseButton type="submit" size="lg" :disabled="status === 'submitting'" class="self-start">
            {{ status === 'submitting' ? 'Sending…' : 'Send message' }}
        </BaseButton>
    </form>
</template>
