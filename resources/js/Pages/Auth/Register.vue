<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import SocialAuth from '@/Components/Auth/SocialAuth.vue';
import BackLink from '@/Components/Auth/BackLink.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <BackLink />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Create your account</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Start invoicing in minutes — free, no card required.</p>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div class="space-y-1.5">
                <InputLabel for="name" value="Full name" />
                <TextInput id="name" type="text" v-model="form.name" required autofocus autocomplete="name" placeholder="Jordan Avery" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-1.5">
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" v-model="form.email" required autocomplete="username" placeholder="you@company.com" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <InputLabel for="password" value="Password" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="new-password" placeholder="••••••••" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-1.5">
                <InputLabel for="password_confirmation" value="Confirm password" />
                <TextInput id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <AuthButton label="Create account" :processing="form.processing" />
        </form>

        <SocialAuth label="Or sign up with" />

        <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Already have an account?
            <Link :href="route('login')" class="font-semibold text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300">Sign in</Link>
        </p>
    </GuestLayout>
</template>
