<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Verify your email</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            Thanks for signing up. Click the link in the email we just sent to finish setting up your account. We can send another if it didn't arrive.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mt-6 rounded-xl border border-emerald-200/60 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-400"
        >
            A new verification link has been sent to your email address.
        </div>

        <form @submit.prevent="submit" class="mt-8">
            <AuthButton label="Resend verification email" :processing="form.processing" />
        </form>

        <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="font-semibold text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                >Log out</Link
            >
        </p>
    </GuestLayout>
</template>
