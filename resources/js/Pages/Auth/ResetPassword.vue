<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <h1 class="font-display text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Choose a new password</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Make it something you'll remember this time.</p>

        <form @submit.prevent="submit" class="mt-8 space-y-5">
            <div class="space-y-1.5">
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <InputLabel for="password" value="New password" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="new-password" placeholder="••••••••" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-1.5">
                <InputLabel for="password_confirmation" value="Confirm password" />
                <TextInput id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <AuthButton label="Reset password" :processing="form.processing" />
        </form>
    </GuestLayout>
</template>
