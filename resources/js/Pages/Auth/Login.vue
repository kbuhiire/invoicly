<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import SocialAuth from '@/Components/Auth/SocialAuth.vue';
import BackLink from '@/Components/Auth/BackLink.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <BackLink />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Welcome back</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Sign in to your Invoicly account.</p>

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

            <div class="space-y-1.5">
                <InputLabel for="password" value="Password" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="current-password" placeholder="••••••••" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                >
                    Forgot password?
                </Link>
            </div>

            <AuthButton label="Sign in" :processing="form.processing" />
        </form>

        <SocialAuth label="Or continue with" />

        <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            New to Invoicly?
            <Link href="/register" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300">Create an account</Link>
        </p>
    </GuestLayout>
</template>
