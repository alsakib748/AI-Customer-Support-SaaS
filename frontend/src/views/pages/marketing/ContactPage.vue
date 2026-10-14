<script setup>
import { useSeo } from '@/composables/useSeo';
import ContactForm from '@/components/marketing/ContactForm.vue';

useSeo({
    title: 'Contact',
    description: 'Talk to the SupportAI team, ask a question or request a demo of AI-powered customer support.',
    path: '/contact'
});
</script>

<template>
    <section class="bg-gradient-to-b from-alt to-page py-14 md:py-20" aria-labelledby="contact-h1">
        <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.2fr] lg:gap-16 lg:px-8">
            <div class="flex flex-col gap-4">
                <p class="text-[13px] font-semibold tracking-wide text-accent uppercase">Contact</p>
                <h1 id="contact-h1" class="text-4xl leading-[1.1] font-bold tracking-tight md:text-5xl">Talk to our team.</h1>
                <p class="max-w-md text-base leading-relaxed text-body md:text-lg">Ask a question, or tell us about your support setup and request a demo.</p>
            </div>
            <div class="rounded-[22px] border border-line bg-card p-6 md:p-8">
                <ContactForm />
            </div>
        </div>
    </section>
</template>
