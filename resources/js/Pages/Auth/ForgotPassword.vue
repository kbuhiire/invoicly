<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Reset your password</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            Tell us your email and we'll send a link to choose a new one.
        </p>

        <div
            v-if="status"
            class="mt-6 rounded-xl border border-emerald-200/60 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-400"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div class="space-y-1.5">
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" v-model="form.email" required autofocus autocomplete="username" placeholder="you@company.com" />
                <InputError :message="form.errors.email" />
            </div>

            <AuthButton label="Send reset link" :processing="form.processing" />
        </form>

        <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Remembered it?
            <Link :href="route('login')" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300">Back to sign in</Link>
        </p>
    </GuestLayout>
</template>
